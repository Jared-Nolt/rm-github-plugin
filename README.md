# RM GitHub Update Integration

This plugin is configured to receive automatic update notifications directly from the GitHub repository `Jared-Nolt/rm-github-plugin`.

## 🟢 Project Configuration
* **Username:** Jared-Nolt
* **Repository:** rm-github-plugin
* **Prefix:** RM_
* **Logic Location:** `/updater/updater.php`

## 🛠 How to Publish an Update
To trigger an update notification on live WordPress sites, follow these exact steps:

1.  **Bump Version:** Open `rm-github-plugin.php` and change the `Version:` header (e.g., from `1.0.0` to `1.0.1`).
2.  **Commit & Push:** Push your code changes to the main branch on GitHub.
3.  **Create Release:** * Go to your GitHub repository page.
    * Click **Releases** (on the right sidebar).
    * Click **Draft a new release**.
    * **Tag version:** Type the version number exactly (e.g., `1.0.1`).
    * **Release title:** Give it a name (e.g., `Version 1.0.1`).
    * **Description:** Add your changelog notes here.
4.  **Publish:** Click **Publish release**.

## ⚠️ Removing the "Check for Updates" Link
For production environments where you want a cleaner UI, you should disable the manual "Check for Updates" link. 

1.  Open `updater/updater.php`.
2.  Find the section labeled `FORCE UPDATE CODE START`.
3.  Delete or comment out the following two lines:
    ```php
    add_filter( "plugin_action_links_{$this->basename}", [ $this, 'add_check_link' ] );
    add_action( 'admin_init', [ $this, 'process_manual_check' ] );
    ```

## 🔍 Troubleshooting
* **Update not showing?** WordPress caches update checks for ~12 hours. Use the "Check for Updates" link on the Plugins page to force a refresh.
* **404 Error on Download?** Ensure your GitHub repository is set to **Public**. Private repositories require an extra Access Token in the header logic.
* **Version Compare:** Ensure the GitHub Tag version is mathematically higher than the version number in your local plugin file.
* **Private Repos:** Add a token (with `repo` scope) via `define('RM_GH_TOKEN', 'your-token');` in `wp-config.php` or filter `rm_github_token`. Auth headers are applied automatically to metadata and zip downloads.
* **Folder Naming:** The updater now normalizes the extracted folder name to `rm-github-plugin` so updates overwrite the existing plugin instead of installing a new folder.