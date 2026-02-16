<?php
class RM_Plugin_Updater {
    private $file;
    private $plugin_slug;
    private $basename;
    private $gh_token;

    // --- CONFIGURATION ---
    private $gh_user = 'Jared-Nolt'; 
    private $gh_repo = 'rm-github-plugin'; 
    // ---------------------

    public function __construct( $file ) {
        $this->file = $file;
        $this->basename = plugin_basename( $file );

        // Derive a reliable slug even if the plugin lives directly in plugins/ (dirname would be '.').
        $slug = trim( dirname( $this->basename ), '/' );
        $this->plugin_slug = ( $slug && $slug !== '.' ) ? $slug : basename( $this->basename, '.php' );
        // Allow token via wp-config.php define('RM_GH_TOKEN', 'xxx') or filter('rm_github_token') for private repos/rate limits
        $this->gh_token = defined( 'RM_GH_TOKEN' ) ? RM_GH_TOKEN : apply_filters( 'rm_github_token', '' );

        // Run before WordPress saves the transient so our data is persisted.
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_popup' ], 10, 3 );
        add_filter( 'http_request_args', [ $this, 'maybe_authenticate_download' ], 10, 2 );
        add_filter( 'upgrader_source_selection', [ $this, 'ensure_correct_folder' ], 10, 4 );

        /**
         * FORCE UPDATE CODE START
         * NOTE: Remove the two lines below before deploying to a production site 
         * if you want to prevent users from manually clearing the update cache.
         */
        add_filter( "plugin_action_links_{$this->basename}", [ $this, 'add_check_link' ] );
        add_action( 'admin_init', [ $this, 'process_manual_check' ] );
        /** FORCE UPDATE CODE END **/
    }

    private function get_github_release() {
        $url = "https://api.github.com/repos/{$this->gh_user}/{$this->gh_repo}/releases/latest";
        $headers = [
            'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
            'Accept'     => 'application/vnd.github+json',
        ];
        if ( $this->gh_token ) {
            $headers['Authorization'] = 'token ' . $this->gh_token;
        }
        $args = [
            'headers' => $headers,
            'timeout' => 8,
        ];
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) return false;
        if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return false;
        return json_decode( wp_remote_retrieve_body( $response ) );
    }

    public function check_update( $transient ) {
        if ( empty( $transient->checked ) || ! isset( $transient->checked[ $this->basename ] ) ) return $transient;
        $release = $this->get_github_release();
        if ( ! $release || ! isset( $release->tag_name ) || empty( $release->zipball_url ) ) return $transient;

        $current_version = $transient->checked[ $this->basename ];
        $new_version = ltrim( $release->tag_name, 'v' );

        if ( version_compare( $new_version, $current_version, '>' ) ) {
            $obj = new stdClass();
            $obj->slug = $this->plugin_slug;
            $obj->plugin = $this->basename;
            $obj->new_version = $new_version;
            $obj->url = "https://github.com/{$this->gh_user}/{$this->gh_repo}";
            $obj->package = $release->zipball_url;
            $transient->response[ $this->basename ] = $obj;
        }
        return $transient;
    }

    public function plugin_popup( $result, $action, $args ) {
        if ( $action !== 'plugin_information' || $args->slug !== $this->plugin_slug ) return $result;
        $release = $this->get_github_release();
        if ( ! $release ) return $result;

        $changelog = $release->body ?? '';

        $res = new stdClass();
        $res->name = 'RM GitHub Plugin';
        $res->slug = $this->plugin_slug;
        $res->version = ltrim( $release->tag_name, 'v' );
        $res->download_link = $release->zipball_url;
        $res->sections = [
            'description' => 'Updates hosted on GitHub.',
            // Sanitize release body to avoid arbitrary HTML from GitHub notes
            'changelog'   => wp_kses_post( wpautop( $changelog ) ),
        ];
        return $res;
    }

    /**
     * Add auth headers to GitHub API and package downloads when a token is configured.
     */
    public function maybe_authenticate_download( $args, $url ) {
        if ( ! $this->gh_token ) return $args;
        $is_github = strpos( $url, 'github.com' ) !== false || strpos( $url, 'api.github.com' ) !== false;
        if ( ! $is_github ) return $args;

        if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
            $args['headers'] = [];
        }
        // Preserve existing headers and add auth + UA for rate limits/private repos
        $args['headers']['Authorization'] = 'token ' . $this->gh_token;
        $args['headers']['User-Agent'] = $args['headers']['User-Agent'] ?? 'WordPress/' . get_bloginfo( 'version' );
        return $args;
    }

    /**
     * Ensure the unpacked folder matches the plugin slug so updates overwrite correctly.
     */
    public function ensure_correct_folder( $source, $remote_source, $upgrader, $hook_extra ) {
        if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) return $source;
        $desired = trailingslashit( $remote_source ) . $this->plugin_slug;
        $source = untrailingslashit( $source );

        if ( basename( $source ) === $this->plugin_slug ) return $source; // Already correct

        // Try to rename the extracted folder to the plugin slug to match WordPress expectations
        if ( @rename( $source, $desired ) ) {
            return $desired;
        }

        // Fallback: if rename fails, leave as-is to avoid breaking the upgrade
        return $source;
    }

    // --- MANUAL CHECK LOGIC ---
    public function add_check_link( $links ) {
        $check_url = add_query_arg( [ 'gh_check' => $this->plugin_slug, 'nonce' => wp_create_nonce( 'gh_check' ) ], admin_url( 'plugins.php' ) );
        $links['check_update'] = '<a href="' . esc_url( $check_url ) . '">Check for Updates</a>';
        return $links;
    }

    public function process_manual_check() {
        $gh_check = sanitize_text_field( wp_unslash( $_GET['gh_check'] ?? '' ) );
        $nonce    = sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) );

        if ( $gh_check !== $this->plugin_slug ) return;
        if ( ! current_user_can( 'update_plugins' ) || ! wp_verify_nonce( $nonce, 'gh_check' ) ) return;
        
        delete_site_transient( 'update_plugins' );
        wp_redirect( admin_url( 'plugins.php?updated=1' ) );
        exit;
    }
}