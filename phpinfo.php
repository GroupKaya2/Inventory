<?php
echo "<h2>MySQLi Extension Check</h2>";
if (extension_loaded('mysqli')) {
    echo "<p style='color: green;'>✓ MySQLi extension is LOADED</p>";
} else {
    echo "<p style='color: red;'>✗ MySQLi extension is NOT LOADED</p>";
    echo "<p>You need to enable it in your php.ini file by uncommenting: extension=mysqli</p>";
}

echo "<h2>PHP Configuration File</h2>";
$iniFile = php_ini_loaded_file();
echo "<p>Loaded php.ini: <strong>" . ($iniFile ? $iniFile : 'Not found') . "</strong></p>";

echo "<h2>MySQLi Functions Available</h2>";
if (function_exists('mysqli_connect')) {
    echo "<p style='color: green;'>✓ mysqli_connect() is available</p>";
} else {
    echo "<p style='color: red;'>✗ mysqli_connect() is NOT available</p>";
}
?>

