<?php
class RM_Plugin_Updater {
    private $file;
    private $plugin_slug;
    private $basename;

    // --- CONFIGURATION ---
    private $gh_user = 'Jared-Nolt'; 
    private $gh_repo = 'rm-github-plugin'; 
    // ---------------------

    public function __construct( $file ) {
        $this->file = $file;
        $this->basename = plugin_basename( $file );
        $this->plugin_slug = dirname( $this->basename );

        add_filter( 'site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_popup' ], 10, 3 );

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
        $args = [ 'headers' => [ 'User-Agent' => 'WordPress/' . get_bloginfo('version') ] ];
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) return false;
        return json_decode( wp_remote_retrieve_body( $response ) );
    }

    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;
        $release = $this->get_github_release();
        if ( ! $release || ! isset($release->tag_name) ) return $transient;

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

        $res = new stdClass();
        $res->name = 'RM GitHub Plugin';
        $res->slug = $this->plugin_slug;
        $res->version = ltrim( $release->tag_name, 'v' );
        $res->download_link = $release->zipball_url;
        $res->sections = [
            'description' => 'Updates hosted on GitHub.',
            'changelog'   => $release->body
        ];
        return $res;
    }

    // --- MANUAL CHECK LOGIC ---
    public function add_check_link( $links ) {
        $check_url = add_query_arg( [ 'gh_check' => $this->plugin_slug, 'nonce' => wp_create_nonce( 'gh_check' ) ], admin_url( 'plugins.php' ) );
        $links['check_update'] = '<a href="' . esc_url( $check_url ) . '">Check for Updates</a>';
        return $links;
    }

    public function process_manual_check() {
        if ( ( $_GET['gh_check'] ?? '' ) !== $this->plugin_slug ) return;
        if ( ! current_user_can( 'update_plugins' ) || ! wp_verify_nonce( $_GET['nonce'] ?? '', 'gh_check' ) ) return;
        
        delete_site_transient( 'update_plugins' );
        wp_redirect( admin_url( 'plugins.php?updated=1' ) );
        exit;
    }
}