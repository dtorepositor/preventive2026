<?php

namespace App\Data;

class ItemChecklistTemplate
{
    private static function normalizeChecklistType(?string $type): string
    {
        $normalized = strtolower(trim((string) $type));

        if ($normalized === 'server') {
            return 'server';
        }

        if (in_array($normalized, ['ip_phone', 'ip-phone', 'ipphone', 'ip phones', 'ip_phones'], true)) {
            return 'ip_phone';
        }

        if (in_array($normalized, ['network_device', 'network-device', 'networkdevice', 'network device', 'network devices'], true)) {
            return 'network_device';
        }

        if (in_array($normalized, ['wifi', 'wi-fi', 'wireless'], true)) {
            return 'wifi';
        }

        if ($normalized === 'ups') {
            return 'ups';
        }

        if ($normalized === 'cctv') {
            return 'cctv';
        }

        return 'pc';
    }

    /**
     * Returns the default tasks grouped by item_no with their descriptions
     * Structure: [item_no => ['task' => 'Task Name', 'descriptions' => ['desc1', 'desc2', ...]]]
     */
    public static function defaultTasks(?string $type = 'pc'): array
    {
        $normalizedType = self::normalizeChecklistType($type);

        if ($normalizedType === 'server') {
            return self::serverTasks();
        }

        if ($normalizedType === 'ip_phone') {
            return self::ipPhoneTasks();
        }

        if ($normalizedType === 'network_device') {
            return self::networkDeviceTasks();
        }

        if ($normalizedType === 'wifi') {
            return self::wifiTasks();
        }

        if ($normalizedType === 'ups') {
            return self::upsTasks();
        }

        if ($normalizedType === 'cctv') {
            return self::cctvTasks();
        }

        return self::pcTasks();
    }

    private static function pcTasks(): array
    {
        return [
            1 => [
                'task' => 'SYSTEM BOOT',
                'descriptions' => [
                    'Boot system from a cold start. Monitor for errors and speed of entire boot process.',
                ],
            ],
            2 => [
                'task' => 'System Log-in',
                'descriptions' => [
                    'Monitor for errors, monitor log-in script',
                ],
            ],
            3 => [
                'task' => 'Network Settings',
                'descriptions' => [
                    'TCIP/IP or IPX settings are correct.',
                    'Domain Name',
                    'Security Settings',
                    'Client Configurations',
                    'Computer Name',
                ],
            ],
            4 => [
                'task' => 'Computer Hardware Settings',
                'descriptions' => [
                    'Verify device manager settings',
                    'BOIS up-to-date',
                    'Hard Disk',
                    'DVD, CD/RW drive firmware up-to-date',
                    'Memory is OK',
                    'Laptop: battery run time is normal',
                ],
            ],
            5 => [
                'task' => 'Browser/Proxy Settings',
                'descriptions' => [
                    'Verify proper settings and operation',
                ],
            ],
            6 => [
                'task' => 'Proper Software loads',
                'descriptions' => [
                    'Required software is installed and operating',
                ],
            ],
            7 => [
                'task' => 'Viruses and Malware',
                'descriptions' => [
                    'Anti-Virus installed',
                    'Virus scan done',
                ],
            ],
            8 => [
                'task' => 'Clearance',
                'descriptions' => [
                    'Unused software remove',
                    'Temporary files remove',
                    'Recycle Bin and Caches emptied',
                    'Periphery devices clean',
                ],
            ],
            9 => [
                'task' => 'Interiors and cleaning',
                'descriptions' => [
                    'Dust remove',
                    'No loose parts',
                    'Airflow is OK',
                    'Cables unplugged and re-plugged',
                    'Fans are operating',
                ],
            ],
            10 => [
                'task' => 'Peripheral devices',
                'descriptions' => [
                    'Mouse',
                    'Keyboard',
                    'Monitor',
                    'UPS',
                    'Printer',
                    'Telephone Extension',
                    'Fax',
                ],
            ],
        ];
    }

    private static function serverTasks(): array
    {
        return [
            1 => [
                'task' => 'Data, Software and System checks',
                'descriptions' => [
                    'Check backups are working',
                    'Check and update OS',
                    'Update your control panel',
                    'Check and update applications',
                    'Check remote management tools',
                    'Remote console',
                    'Remote reboot',
                    'Rescue mode',
                    'Check server usage',
                    'Disk',
                    'CPU',
                    'RAM',
                    'Network',
                    'Review user accounts',
                    'Free up server storage space',
                ],
            ],
            2 => [
                'task' => 'Security Checks',
                'descriptions' => [
                    'Change server passwords',
                    'Firewall installed',
                    'Perform a server malware scan',
                ],
            ],
            3 => [
                'task' => 'Hardware Checks',
                'descriptions' => [
                    'Check fans and power supplies',
                    'Check RAID fault tolerance',
                    'Check for disk read errors',
                    'Perform all driver, controller firmware, and storage management application updates',
                    'Run system consistency check',
                    'Replace any drives that have failed or are showing signs of failing',
                    'Check cable integrity',
                    'Cables are securely fixed at each connection point',
                    'Cables are not twisted or under unnecessary strain',
                    'Cables are all in good condition',
                    'Check A/C unit at the facility',
                ],
            ],
        ];
    }

    private static function ipPhoneTasks(): array
    {
        return [
            1 => [
                'task' => 'Hardware',
                'descriptions' => [
                    'IP phone is configured properly',
                    'IP phone free dust and dirt',
                    'Are all buttons functional',
                    'No loose parts',
                    'Properly mounted on the wall',
                ],
            ],
            2 => [
                'task' => 'Wire and Cables',
                'descriptions' => [
                    'No tear and wear',
                    'Properly installed',
                    'Well connected',
                ],
            ],
            3 => [
                'task' => 'Software/Firmware',
                'descriptions' => [
                    'It is updated',
                    'Free from free errors',
                    'Properly configured to its specification',
                ],
            ],
            5 => [
                'task' => 'Surge Protection',
                'descriptions' => [
                    'Power supply properly installed',
                    'Free from dust and particulates',
                    'Any wear and tear or exposed power cord',
                ],
            ],
        ];
    }

    private static function networkDeviceTasks(): array
    {
        return [
            1 => [
                'task' => 'Physical Inspection',
                'descriptions' => [
                    'Device, mounting, and ventilation are in good condition',
                    'Device is clean and free from loose parts or debris',
                    'Power and network cabling are secure and undamaged',
                ],
            ],
            2 => [
                'task' => 'Connectivity and Ports',
                'descriptions' => [
                    'Device is reachable on the network',
                    'Port indicators and active links are functioning normally',
                    'No obvious packet errors or performance issues observed',
                ],
            ],
            3 => [
                'task' => 'Configuration and Settings',
                'descriptions' => [
                    'IP address, gateway, and VLAN settings verified',
                    'Device configuration backed up or confirmed available',
                    'Management access and security settings reviewed',
                ],
            ],
            4 => [
                'task' => 'Firmware, Logs, and Monitoring',
                'descriptions' => [
                    'Firmware version checked against approved version',
                    'System logs reviewed with no critical alarms',
                    'Temperature, power, and monitoring status are normal',
                ],
            ],
        ];
    }

    private static function wifiTasks(): array
    {
        return [
            1 => [
                'task' => 'Physical Inspection',
                'descriptions' => [
                    'Access point securely mounted',
                    'Power adapter or PoE source working properly',
                    'Cabling intact and properly connected',
                ],
            ],
            2 => [
                'task' => 'Wireless Configuration',
                'descriptions' => [
                    'SSID or WiFi name configured correctly',
                    'Channel and supported band settings verified',
                    'IP addressing and VLAN settings verified',
                ],
            ],
            3 => [
                'task' => 'Connectivity and Coverage',
                'descriptions' => [
                    'Device reachable on the network',
                    'Clients can connect and access the network',
                    'Signal coverage acceptable in the service area',
                ],
            ],
            4 => [
                'task' => 'Security and Firmware',
                'descriptions' => [
                    'WiFi password or security settings verified',
                    'Firmware updated to approved version',
                    'Logs checked with no critical alarms',
                ],
            ],
        ];
    }

    private static function upsTasks(): array
    {
        return [
            1 => [
                'task' => 'Physical Inspection',
                'descriptions' => [
                    'UPS casing and indicators are in good condition',
                    'Power cable and output connections are secure',
                    'No unusual noise, odor, or visible damage',
                ],
            ],
            2 => [
                'task' => 'Power and Battery',
                'descriptions' => [
                    'Battery or backup status is normal',
                    'Input and output power operation verified',
                    'Total load is within rated capacity',
                ],
            ],
            3 => [
                'task' => 'Configuration and Monitoring',
                'descriptions' => [
                    'Network or LAN monitoring settings verified if supported',
                    'Firmware or management settings checked',
                    'Logs or alarms reviewed with no critical issue',
                ],
            ],
        ];
    }

    private static function cctvTasks(): array
    {
        return [
            1 => [
                'task' => 'Physical Inspection',
                'descriptions' => [
                    'Camera housing and mounting are secure',
                    'Power and network cabling are intact',
                    'Lens and enclosure are clean with no visible damage',
                ],
            ],
            2 => [
                'task' => 'Video and Connectivity',
                'descriptions' => [
                    'Camera reachable on the network',
                    'Live video feed is available and clear',
                    'IP address and VLAN settings verified',
                ],
            ],
            3 => [
                'task' => 'Configuration and Recording',
                'descriptions' => [
                    'Device configuration matches assigned purpose',
                    'Recording or stream settings checked',
                    'Logs reviewed with no critical alarms',
                ],
            ],
        ];
    }

    /**
     * Returns tasks and entries in a flattened array for easier iteration
     * Each entry includes: item_no, task, description, task_index, entry_index
     */
    public static function flattenedEntries(): array
    {
        return self::flattenedEntriesForType('pc');
    }

    public static function flattenedEntriesForType(?string $type = 'pc'): array
    {
        $result = [];
        $entryIndex = 0;
        
        foreach (self::defaultTasks($type) as $itemNo => $taskData) {
            $taskIndex = 0;
            foreach ($taskData['descriptions'] as $description) {
                $result[] = [
                    'item_no' => $itemNo,
                    'task' => $taskData['task'],
                    'description' => $description,
                    'entry_index' => $entryIndex,
                    'task_index' => $taskIndex,
                    'is_first_in_task' => $taskIndex === 0,
                    'description_count' => count($taskData['descriptions']),
                ];
                $taskIndex++;
                $entryIndex++;
            }
        }
        
        return $result;
    }

    /**
     * Legacy method for backward compatibility
     * @deprecated Use defaultTasks() or flattenedEntries() instead
     */
    public static function defaultEntries(): array
    {
        return self::defaultEntriesForType('pc');
    }

    public static function defaultEntriesForType(?string $type = 'pc'): array
    {
        $sort = 0;
        $entries = [];
        
        foreach (self::defaultTasks($type) as $itemNo => $taskData) {
            foreach ($taskData['descriptions'] as $description) {
                $entries[] = [
                    'item_no' => $itemNo,
                    'task' => $taskData['task'],
                    'description' => $description,
                    'sort_order' => $sort++,
                ];
            }
        }
        
        return $entries;
    }
}
