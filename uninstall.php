<?php
if (class_exists('featurecode')) {
    $fcl = new featurecode('keylock', 'keylock_lock');
    $fcu = new featurecode('keylock', 'keylock_unlock');
    $fct = new featurecode('keylock', 'keylock_toggle');
    $fcs = new featurecode('keylock', 'keylock_setpass');

    $fcl->delete();
    $fcu->delete();
    $fct->delete();
    $fcs->delete();

    unset($fcl);
    unset($fcu);
    unset($fct);
    unset($fcs);
}

global $astman;
global $amp_conf;

// Replace sql() with PDO
try {
    $db = FreePBX::Database();
    $sql = "SELECT * FROM users";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $userresults = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    //add details to astdb
    if ($astman) {
            foreach($userresults as $usr) {
                    $extension = $usr['extension'];
                    $astman->database_deltree("KEYLOCK/".$extension);
            }
    } 
} catch(\Exception $e) {
    // Ignore errors on uninstall
}
?>