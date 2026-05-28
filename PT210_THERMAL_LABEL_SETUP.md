# PT-210 Thermal Label Printing Setup

This app now has direct server-side label printing for item checklists.

## 1. Enable PHP extensions in XAMPP

Open the PHP configuration used by XAMPP CLI and Apache, usually:

```ini
C:\xampp\php\php.ini
```

Enable these lines by removing the leading semicolon:

```ini
extension=gd
extension=intl
```

Restart Apache after changing `php.ini`.

## 2. Install Composer packages

From this project folder:

```powershell
composer require mike42/escpos-php simplesoftwareio/simple-qrcode picqer/php-barcode-generator
```

If Composer still complains, confirm the CLI PHP is the XAMPP PHP:

```powershell
php --ini
php -m
```

`php -m` must list `gd` and `intl`.

## 3. Printer share setting

The code defaults to:

```env
PT210_PRINTER_SHARE=smb://localhost/PT210
```

Add this to `.env` if you need to change it. The Windows printer must be shared as `PT210`.

## 4. Test print

Open this route in the browser:

```text
http://127.0.0.1:8000/print-test
```

It sends this text directly to the PT-210:

```text
PT-210 TEST PRINT SUCCESSFUL
```

## 5. Added routes

```php
// routes/api.php
Route::post('/item-checklist/{id}/print-qr-code', [ApiController::class, 'printItemChecklistQrCode']);
Route::post('/item-checklist/{id}/print-barcode', [ApiController::class, 'printItemChecklistBarcode']);

// routes/web.php
Route::get('/scan/checklist/{code}', [ApiController::class, 'redirectScannedChecklist'])->name('scan.checklist');
Route::get('/print-test', [ApiController::class, 'printTest'])->name('print.test');
```

## 6. Dropdown labels

The item checklist Print dropdown now contains exactly:

```text
Print as Word
Print as PDF
Print QR Code
Print Barcode
```

No "View QR Code PDF" or "View Barcode PDF" entries were added.
