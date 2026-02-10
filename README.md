# KeyLock Module for FreePBX

**KeyLock** is a security module for FreePBX that allows users to lock their extensions to prevent unauthorized outbound calls (e.g., international calls, mobile numbers) while still allowing internal calls.

This version has been modernized to support **PHP 8.x** and uses a high-priority injection method to override other routing modules (such as Boss Secretary).

## 🚀 Features

* **Extension Locking:** Users can lock their phone preventing specific outbound routes.
* **Password Protection:** Users can set their own PIN for locking/unlocking.
* **Pattern Based:** Administrators define exactly which dial patterns are blocked (e.g., `_9.`, `_00.`).
* **High Priority Routing:** Injects rules directly into `from-internal` to bypass standard routing restrictions.
* **Feature Codes:** Simple usage via telephone keypad (*57, *58, etc.).

## 📋 Requirements

* FreePBX 15+ / 16+ / 17+
* Asterisk 16+
* PHP 7.4 or **PHP 8.x** (Fully compatible)

## 🛠 Installation

1.  **Upload the Module:**
    Copy the `keylock` directory into your FreePBX modules folder:
    ```bash
    /var/www/html/admin/modules/keylock/
    ```

2.  **Install via Console:**
    Run the following commands to register the module and create the database tables:
    ```bash
    fwconsole ma install keylock
    fwconsole reload
    ```

3.  **Verify Permissions:**
    Ensure the asterisk user owns the files:
    ```bash
    chown -R asterisk:asterisk /var/www/html/admin/modules/keylock
    ```

## ⚙️ Configuration

### 1. Admin Setup
Go to **Admin** > **Key Lock** in the FreePBX web interface.

* **Patterns:** Enter the Asterisk patterns you want to block when the lock is active.
* **Format:** One pattern per line. Use standard Asterisk pattern syntax (prepend `_` if needed, though the code handles it).
* **Example:**
    ```text
    9[2-9].      ; Blocks calls starting with 9 followed by 2-9 (External/Mobile)
    00.          ; Blocks international calls
    ```
* Click **Save** and **Apply Config**.

### 2. User Usage (Feature Codes)
Users can manage their lock status from their phones using these default codes:

* **`*59` (Set Password):** First time setup. Sets the PIN for the extension.
* **`*57` (Lock):** Locks the extension. Requires PIN.
* **`*58` (Unlock):** Unlocks the extension. Requires PIN.
* **`*56` (Toggle):** Switches between Locked/Unlocked states.

## 🔧 Technical Details (Developer Notes)

### Routing Logic
This module uses a **Direct Injection Strategy** to ensure it takes precedence over other modules like "Boss Secretary" or standard Outbound Routes.

Instead of using standard `includes` (which have lower priority than local context rules), KeyLock writes directly into the `[from-internal]` context.

**Workflow:**
1.  **Pattern Match:** When a user dials a number matching a blocked pattern (e.g., `9888111`), the KeyLock rule in `from-internal` intercepts it *before* standard routing.
2.  **Macro Check:** The call is sent to `macro-keylock-check`.
    * If **LOCKED**: The macro plays an "Access Denied" message and hangs up.
    * If **UNLOCKED**: The macro returns control to the dialplan.
3.  **Handoff:** Upon return, the call is explicitly directed to `outbound-allroutes` to continue normal processing (Trunk selection).

### PHP 8 Compatibility Fixes
This version includes fixes for strict typing in PHP 8:
* Null coalescing (`?? ''`) in `htmlspecialchars` to prevent UI crashes on empty configs.
* String casting `(string)` in `explode` functions to prevent reload failures when the database is empty.

## 📂 File Structure

* `functions.inc.php`: Core logic, dialplan generation, and context injection.
* `page.keylock.php`: Web GUI for admin configuration.
* `install.php / install.sql`: Database schema setup.
* `uninstall.php / uninstall.sql`: Cleanup scripts.

## 📜 License & Credits
* **Original Author:** Maikel Salazar (Sponsored by TI Soluciones)
* **Version:** 0.4.1
* **Category:** Inbound/Outbound Call Control
