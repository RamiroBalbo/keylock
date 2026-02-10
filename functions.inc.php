<?php

/********************************************************
 * API FUNCTIONS                                        *
 ********************************************************/

function keylock_get_config($engine)
{
    global $ext; 

    switch($engine) {
    case "asterisk":
        $modulename = "keylock";

    $lock_label = "keylock_lock";
    $unlock_label = "keylock_unlock";
    $toggle_label = "keylock_toggle";
    $setpass_label = "keylock_setpass";

    if (class_exists('featurecode')) {
        $fcl = new featurecode($modulename, $lock_label);
        $fcu = new featurecode($modulename, $unlock_label);
        $fct = new featurecode($modulename, $toggle_label);
        $fcs = new featurecode($modulename, $setpass_label);

        $lock = $fcl->getCodeActive();
        $lock_rgxp = "_" . $lock . ".";
        $unlock = $fcu->getCodeActive();
        $unlock_rgxp = "_" . $unlock . ".";
        $toggle = $fct->getCodeActive();
        $setpass = $fcs->getCodeActive();
    } else {
        $lock = '*57';
        $unlock = '*58';
        $toggle = '*56';
        $setpass = '*59';
    }
    
    $unknown = "s"; 

    $ctx_toggle = "keylock-toggle";
    $ctx_lock = "keylock-lock";
    $ctx_unlock = "keylock-unlock";
    $ctx_hints = "keylock-hints";
    $macro_check = "macro-keylock-check";
    $macro_setpass = "macro-keylock-setpass";
    $macro_setpass_name = "keylock-setpass";
    $ctx_keylock = "ext-keylock";
    $ctx_setpass = "keylock-setpass";

    // --- MACRO DE VERIFICACIÓN (La lógica del bloqueo) ---
    $ext->add($macro_check, $unknown, '', new ext_noop("Checking block..."));
    $ext->add($macro_check, $unknown, '', new ext_set("me", '${CALLERID(num)}'));
    // Consultamos la base de datos
    $ext->add($macro_check, $unknown, '', new ext_gotoif('${DB(KEYLOCK/${me}/locked)}', "blocked", "unblocked"));
    
    // Si está bloqueado:
    $ext->add($macro_check, $unknown, 'blocked', new ext_noop("Blocked by Keylock..."));
    $ext->add($macro_check, $unknown, '', new ext_answer());
    $ext->add($macro_check, $unknown, '', new ext_playback("security"));
    $ext->add($macro_check, $unknown, '', new ext_playback("activated"));
    $ext->add($macro_check, $unknown, '', new ext_hangup()); // Cortamos la llamada aquí
    
    // Si NO está bloqueado:
    // IMPORTANTE: Como estamos en from-internal, no hacemos nada y dejamos que el dialplan siga (Return)
    $ext->add($macro_check, $unknown, 'unblocked', new ext_noop("Keylock Passed... Calling..."));
    // Al ser un Macro/Gosub, si termina sin Hangup, la llamada vuelve al contexto original y sigue buscando rutas

    // --- CONTEXTOS DE GESTIÓN (Bloquear/Desbloquear con *) ---
    // (Estos los dejamos igual, funcionan bien en sus propios contextos)

    //Locking context
    $ext->add($ctx_lock, $lock, '', new ext_answer());
    $ext->add($ctx_lock, $lock, '', new ext_set("me", '${CALLERID(num)}')); 
    $ext->add($ctx_lock, $lock, '', new ext_gotoif('${DB_EXISTS(KEYLOCK/${me}/password)}', "exists", "not_exists"));
    $ext->add($ctx_lock, $lock, 'not_exists', new ext_macro($macro_setpass_name));
    $ext->add($ctx_lock, $lock, '', new ext_goto('authenticated'));
    $ext->add($ctx_lock, $lock, 'exists', new ext_set('PASSWORD','${DB(KEYLOCK/${me}/password)}'));
    $ext->add($ctx_lock, $lock, '', new ext_authenticate('${PASSWORD}'));
    $ext->add($ctx_lock, $lock, '', new ext_goto('authenticated'));
    $ext->add($ctx_lock, $lock, 'authenticated', new ext_set('DB(KEYLOCK/${me}/locked)', '1'));
    $ext->add($ctx_lock, $lock, '', new ext_set('STATE', 'BUSY'));
    $ext->add($ctx_lock, $lock, '', new ext_gosub(1, 'sstate',$ctx_lock));
    $ext->add($ctx_lock, $lock, '', new ext_playback("security"));
    $ext->add($ctx_lock, $lock, '', new ext_playback("now")); 
    $ext->add($ctx_lock, $lock, '', new ext_playback("activated")); 
    $ext->add($ctx_lock, $lock, '', new ext_hangup());
    $ext->add($ctx_lock, 'sstate', '', new ext_setvar('DEVSTATE(Custom:KLC${me})','${STATE}'));
    $ext->add($ctx_lock, 'sstate', 'return', new ext_return());

    //Unlocking context
    $ext->add($ctx_unlock, $unlock, '', new ext_answer());
    $ext->add($ctx_unlock, $unlock, '', new ext_set("ME", '${CALLERID(num)}')); 
    $ext->add($ctx_unlock, $unlock, '', new ext_gotoif('${DB(KEYLOCK/${ME}/locked)}', "locked", "unlocked"));
    $ext->add($ctx_unlock, $unlock, 'locked', new ext_playback("security"));
    $ext->add($ctx_unlock, $unlock, '', new ext_playback("activated"));
    $ext->add($ctx_unlock, $unlock, '', new ext_set('PASSWORD','${DB(KEYLOCK/${ME}/password)}'));
    $ext->add($ctx_unlock, $unlock, '', new ext_authenticate('${PASSWORD}'));
    $ext->add($ctx_unlock, $unlock, '', new ext_goto('authenticated'));
    $ext->add($ctx_unlock, $unlock, 'authenticated', new ext_dbdel('KEYLOCK/${ME}/locked'));
    $ext->add($ctx_unlock, $unlock, '', new ext_set('STATE', 'NOT_INUSE'));
    $ext->add($ctx_unlock, $unlock, '', new ext_gosub(1, 'sstate',$ctx_unlock));
    $ext->add($ctx_unlock, $unlock, '', new ext_goto('unlocked'));
    $ext->add($ctx_unlock, $unlock, 'unlocked', new ext_playback("security"));
    $ext->add($ctx_unlock, $unlock, '', new ext_playback("now")); 
    $ext->add($ctx_unlock, $unlock, '', new ext_playback("de-activated")); 
    $ext->add($ctx_unlock, $unlock, '', new ext_hangup());
    $ext->add($ctx_unlock, 'sstate', '', new ext_setvar('DEVSTATE(Custom:KLC${ME})','${STATE}'));
    $ext->add($ctx_unlock, 'sstate', 'return', new ext_return());

    //Toggle context
    $ext->add($ctx_toggle, $toggle, '', new ext_macro('user-callerid'));
    $ext->add($ctx_toggle, $toggle, '', new ext_set("me",'${CALLERID(num)}'));
    $ext->add($ctx_toggle, $toggle, '', new ext_gotoif('${DB(KEYLOCK/${me}/locked)}',"keylock-unlock,$unlock,locked","keylock-lock,$lock,1"));

    //Setpass Macro
    $ext->add($macro_setpass, $unknown, '', new ext_set('ME','${CALLERID(num)}'));
    $ext->add($macro_setpass, $unknown, '', new ext_gotoif('${DB(KEYLOCK/${ME}/password)}','set','read'));
    $ext->add($macro_setpass, $unknown, 'set', new ext_set('PASSWORD','${DB(KEYLOCK/${ME}/password)}'));
    $ext->add($macro_setpass, $unknown, '', new ext_authenticate('${PASSWORD}'));
    $ext->add($macro_setpass, $unknown, '', new ext_goto('notset'));
    $ext->add($macro_setpass, $unknown, 'read', new ext_read('PASSWORD','vm-newpassword'));
    $ext->add($macro_setpass, $unknown, '', new ext_gotoif('$["${PASSWORD}"!=""]','notset','read'));
    $ext->add($macro_setpass, $unknown, 'notset', new ext_set('DB(KEYLOCK/${ME}/password)','${PASSWORD}'));
    $ext->add($macro_setpass, $unknown, '', new ext_playback("vm-passchanged")); 

    $ext->add($ctx_setpass, $setpass, '', new ext_answer());
    $ext->add($ctx_setpass, $setpass, '', new ext_macro($macro_setpass_name));
    $ext->add($ctx_setpass, $setpass, '', new ext_hangup());

    // --- AQUÍ ESTÁ EL CAMBIO IMPORTANTE ---
    // Recuperamos los patrones de la base de datos
    $patterns_raw = keylock_get_patterns();
    $patterns = explode("\n", $patterns_raw);
    
    if(is_array($patterns)){
        foreach ($patterns as $pattern)
        {            
            $pattern = trim($pattern);
            if (!empty($pattern))
            {
                // INYECCIÓN DIRECTA A 'from-internal' (Igual que Boss Secretary)
                // Usamos _ + Patron para que Asterisk lo trate como patrón
                // Al inyectar directamente aquí, competimos de igual a igual.
                // Y como tu patrón (ej: _9[2-9].) es MAS ESPECIFICO que _X., ¡GANAS TÚ!
                
                $ext->add('from-internal', "_".$pattern, '', new ext_macro('keylock-check'));
            }
        }
    }

    // Includes básicos para que funcionen los feature codes (*57, etc)
    // Estos SÍ se incluyen porque no compiten con rutas salientes, son códigos internos
    $ext->addInclude('from-internal-additional', $ctx_hints);
    $ext->addInclude('from-internal-additional', $ctx_toggle);
    $ext->addInclude('from-internal-additional', $ctx_lock);
    $ext->addInclude('from-internal-additional', $ctx_unlock);
    $ext->addInclude('from-internal-additional', $ctx_setpass);
    
    // BORRAMOS el include viejo de ext-keylock porque ya lo inyectamos arriba manualmente
    // $ext->addInclude('from-internal-additional',$ctx_keylock); 

    break;
    }
}

// ... (Tus funciones de base de datos siguen igual) ...
function keylock_get_users() { /* ... */ }
function keylock_get_patterns() { /* ... */ }
function keylock_set_patterns($patterns) { /* ... */ }
?>