<?php

declare(strict_types=1);

$sourceDb = 'pm_archive_db';
$targetDb = 'prevmaincheckdb';

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$quote = static fn (string $identifier): string => '`' . str_replace('`', '``', $identifier) . '`';
$source = $quote($sourceDb);
$target = $quote($targetDb);

$userExists = $pdo->prepare("SELECT 1 FROM {$target}.users WHERE id = ? LIMIT 1");
$identifierExists = $pdo->prepare("SELECT 1 FROM {$target}.psm WHERE identifier = ? LIMIT 1");
$targetVar = $pdo->prepare("
    SELECT psm_var_id
    FROM {$target}.psm_variable
    WHERE psm_id = ? AND name = ?
    LIMIT 1
");

$archiveRows = $pdo->query("
    SELECT a.*
    FROM {$source}.psm a
    LEFT JOIN {$target}.psm p
        ON p.type = a.type
        AND p.template_psm_id = a.template_psm_id
        AND p.name = a.name
        AND p.created_at = a.created_at
        AND IFNULL(p.identifier, '') = IFNULL(a.identifier, '')
    WHERE a.type = 'submission'
        AND a.template_psm_id = 1
        AND p.psm_id IS NULL
    ORDER BY a.psm_id
")->fetchAll();

if ($archiveRows === []) {
    echo "No archive PM records need merging.\n";
    exit(0);
}

$insertPsm = $pdo->prepare("
    INSERT INTO {$target}.psm
        (name, detail, enabled, created_at, type, template_psm_id, created_by, is_locked, identifier)
    VALUES
        (:name, :detail, :enabled, :created_at, :type, :template_psm_id, :created_by, :is_locked, :identifier)
");

$sourceValues = $pdo->prepare("
    SELECT pv.*, var.name AS variable_name, var.psm_id AS variable_template_psm_id
    FROM {$source}.psm_value pv
    JOIN {$source}.psm_variable var ON var.psm_var_id = pv.psm_var_id
    WHERE pv.psm_id = ?
    ORDER BY pv.psm_val_id
");

$insertValue = $pdo->prepare("
    INSERT INTO {$target}.psm_value
        (psm_id, psm_var_id, value, status, created_at)
    VALUES
        (:psm_id, :psm_var_id, :value, :status, :created_at)
");

$insertArchiveValue = $pdo->prepare("
    INSERT INTO {$target}.psm_value_archive
        (psm_val_id, psm_id, psm_var_id, variable_name, value, status, created_at)
    VALUES
        (:psm_val_id, :psm_id, :psm_var_id, :variable_name, :value, :status, :created_at)
");

$sourceRevisions = $pdo->prepare("
    SELECT *
    FROM {$source}.preventive_maintenance_revisions
    WHERE psm_id = ?
    ORDER BY id
");

$insertRevision = $pdo->prepare("
    INSERT INTO {$target}.preventive_maintenance_revisions
        (psm_id, name, detail, values_snapshot, original_created_at, created_at, updated_at)
    VALUES
        (:psm_id, :name, :detail, :values_snapshot, :original_created_at, :created_at, :updated_at)
");

$makeIdentifier = function (?string $identifier) use ($identifierExists): ?string {
    if ($identifier === null || $identifier === '') {
        return $identifier;
    }

    $candidate = $identifier;
    $counter = 2;

    while (true) {
        $identifierExists->execute([$candidate]);
        if (! $identifierExists->fetchColumn()) {
            return $candidate;
        }

        $suffix = '-ARCH' . ($counter === 2 ? '' : $counter);
        $candidate = substr($identifier, 0, 50 - strlen($suffix)) . $suffix;
        $counter++;
    }
};

$merged = [];

$pdo->beginTransaction();

try {
    foreach ($archiveRows as $row) {
        $createdBy = $row['created_by'];
        if ($createdBy !== null) {
            $userExists->execute([$createdBy]);
            $createdBy = $userExists->fetchColumn() ? $createdBy : null;
        }

        $identifier = $makeIdentifier($row['identifier']);

        $insertPsm->execute([
            'name' => $row['name'],
            'detail' => $row['detail'],
            'enabled' => $row['enabled'],
            'created_at' => $row['created_at'],
            'type' => $row['type'],
            'template_psm_id' => $row['template_psm_id'],
            'created_by' => $createdBy,
            'is_locked' => $row['is_locked'],
            'identifier' => $identifier,
        ]);

        $newPsmId = (int) $pdo->lastInsertId();
        $merged[] = [$row['psm_id'], $newPsmId, $row['name'], $identifier];

        $sourceValues->execute([$row['psm_id']]);
        foreach ($sourceValues->fetchAll() as $valueRow) {
            $targetVar->execute([
                $valueRow['variable_template_psm_id'],
                $valueRow['variable_name'],
            ]);
            $targetVarId = $targetVar->fetchColumn();

            if (! $targetVarId) {
                throw new RuntimeException("Missing target variable {$valueRow['variable_name']}");
            }

            $insertValue->execute([
                'psm_id' => $newPsmId,
                'psm_var_id' => $targetVarId,
                'value' => $valueRow['value'],
                'status' => $valueRow['status'],
                'created_at' => $valueRow['created_at'],
            ]);

            $newValueId = (int) $pdo->lastInsertId();
            $insertArchiveValue->execute([
                'psm_val_id' => $newValueId,
                'psm_id' => $newPsmId,
                'psm_var_id' => $targetVarId,
                'variable_name' => $valueRow['variable_name'],
                'value' => $valueRow['value'],
                'status' => $valueRow['status'],
                'created_at' => $valueRow['created_at'],
            ]);
        }

        $sourceRevisions->execute([$row['psm_id']]);
        foreach ($sourceRevisions->fetchAll() as $revision) {
            $insertRevision->execute([
                'psm_id' => $newPsmId,
                'name' => $revision['name'],
                'detail' => $revision['detail'],
                'values_snapshot' => $revision['values_snapshot'],
                'original_created_at' => $revision['original_created_at'],
                'created_at' => $revision['created_at'],
                'updated_at' => $revision['updated_at'],
            ]);
        }
    }

    $pdo->commit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, "Merge failed: {$exception->getMessage()}\n");
    exit(1);
}

foreach ($merged as [$oldId, $newId, $name, $identifier]) {
    echo "Merged archive PSM {$oldId} -> shared PSM {$newId}: {$name}";
    echo $identifier ? " ({$identifier})" : '';
    echo "\n";
}

echo 'Merged ' . count($merged) . " archive PM records.\n";
