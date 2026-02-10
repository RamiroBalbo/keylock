<?php 
// Developed by Maikel Salazar (maikelsalazar at gmail dot com)
// Sponsored by TI Soluciones, http://www.solucionesit.com.ve
// Updated for PHP 8 / FreePBX 17

$dispnum = 'keylock'; //used for switch on config.php
$title = _("Key Lock");

if (isset($_POST["patterns"]))
{
	keylock_set_patterns($_POST["patterns"]);
	// needreload() es deprecated pero suele funcionar como alias. 
    // Si falla, comentar la linea.
	if(function_exists('needreload')) {
        needreload();
    }
}

$patterns = keylock_get_patterns();

echo "<h2>" . $title. "</h2>";

$modulename = "keylock";
$lock_label = "keylock_lock";
$unlock_label = "keylock_unlock";
$toggle_label = "keylock_toggle";
$setpass_label = "keylock_setpass";

$lock = '*57';
$unlock = '*58';
$toggle = '*56';
$setpass = '*59';

if (class_exists('featurecode')) {
    $fcl = new featurecode($modulename, $lock_label);
    $fcu = new featurecode($modulename, $unlock_label);
    $fct = new featurecode($modulename, $toggle_label);
    $fcs = new featurecode($modulename, $setpass_label);

    $lock = $fcl->getCodeActive();
    $unlock = $fcu->getCodeActive();
    $toggle = $fct->getCodeActive();
    $setpass = $fcs->getCodeActive();
}
?>

<form method="post" name="keylock" action="config.php?display=keylock">
<table>
    <tr>
        <td colspan="2" align="center"><b>Patterns:</b> <hr /> </td>
    </tr>
    <tr>
        <td colspan="2"><textarea name="patterns" rows="5" cols="50"><?php echo htmlspecialchars($patterns ?? ''); ?></textarea></td>    </tr>
    <tr>
        <td colspan="2" align="center"> <input type="submit" name="submit" value="Save" />
    </tr>

</table>

<p><strong>Warning:</strong> You must be very carefully with patterns.</p>

<br />
<br />

<div style="font-size:80%;">
<b><?php echo _("Key Lock Toggle:") . "</b> " . $toggle  . "."; ?> <br />
<b><?php echo _("Key Lock:") . "</b> " . $lock  . "."; ?> <br />
<b><?php echo _("Key Unlock:") . "</b> " . $unlock  . "."; ?> <br />
<b><?php echo _("Key Setpass:") . "</b> " . $setpass  . "."; ?>
<p style="font-size:80%;"><?php echo _("You can change all these values in Features Code module") . "."; ?></p>
</div>