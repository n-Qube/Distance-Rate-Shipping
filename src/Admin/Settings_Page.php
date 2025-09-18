<?php
/**
 * Admin settings page for Distance Rate Shipping.
 *
 * @package DRS\Admin
 */

declare( strict_types=1 );

namespace DRS\Admin;

use DRS\Settings\Settings;

/**
 * Handles the admin UI using the Settings API.
 */
class Settings_Page {
    /**
     * Option name for all settings.
     */
    private string $option_name;

    /**
     * Path to the main plugin file.
     */
    private string $plugin_file;

    /**
     * Cached settings instance.
     *
     * @var array<string, mixed>|null
     */
    private ?array $settings_cache = null;

    /**
     * Registered hook suffix for the admin page.
     */
    private ?string $page_hook = null;

    /**
     * Constructor.
     */
    public function __construct( string $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->option_name = Settings::OPTION_NAME;
    }

    /**
     * Wire hooks for the admin page.
     */
    public function init(): void {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register the admin submenu under WooCommerce → Distance Rate.
     */
    public function register_menu(): void {
        $this->page_hook = add_submenu_page(
            'woocommerce',
            __( 'Distance Rate Shipping', 'drs-distance' ),
            __( 'Distance Rate', 'drs-distance' ),
            'manage_woocommerce',
            'drs-distance-rate',
            array( $this, 'render_page' )
        );
    }

    /**
     * Register settings, sections and fields.
     */
    public function register_settings(): void {
        register_setting(
            'drs_distance_rate_settings_group',
            $this->option_name,
            array( $this, 'sanitize_settings' )
        );

        // General tab.
        add_settings_section(
            'drs_general_section',
            __( 'General Options', 'drs-distance' ),
            array( $this, 'render_general_section' ),
            'drs_distance_rate_general'
        );

        add_settings_field(
            'drs_enabled',
            __( 'Enable method', 'drs-distance' ),
            array( $this, 'render_enabled_field' ),
            'drs_distance_rate_general',
            'drs_general_section',
            array( 'label_for' => 'drs_enabled' )
        );

        add_settings_field(
            'drs_method_title',
            __( 'Method title', 'drs-distance' ),
            array( $this, 'render_method_title_field' ),
            'drs_distance_rate_general',
            'drs_general_section',
            array( 'label_for' => 'drs_method_title' )
        );

        add_settings_field(
            'drs_handling_fee',
            __( 'Handling fee', 'drs-distance' ),
            array( $this, 'render_handling_fee_field' ),
            'drs_distance_rate_general',
            'drs_general_section',
            array( 'label_for' => 'drs_handling_fee' )
        );

        add_settings_field(
            'drs_default_rate',
            __( 'Fallback rate', 'drs-distance' ),
            array( $this, 'render_default_rate_field' ),
            'drs_distance_rate_general',
            'drs_general_section',
            array( 'label_for' => 'drs_default_rate' )
        );

        add_settings_field(
            'drs_fallback_enabled',
            __( 'Backup flat rate', 'drs-distance' ),
            array( $this, 'render_fallback_enabled_field' ),
            'drs_distance_rate_general',
            'drs_general_section',
            array( 'label_for' => 'drs_fallback_enabled' )
        );

        add_settings_field(
            'drs_fallback_label',
            __( 'Backup label', 'drs-distance' ),
            array( $this, 'render_fallback_label_field' ),
            'drs_distance_rate_general',
            'drs_general_section',
            array( 'label_for' => 'drs_fallback_label' )
        );

        add_settings_field(
            'drs_fallback_cost',
            __( 'Backup cost', 'drs-distance' ),
            array( $this, 'render_fallback_cost_field' ),
            'drs_distance_rate_general',
            'drs_general_section',
            array( 'label_for' => 'drs_fallback_cost' )
        );

        add_settings_field(
            'drs_distance_unit',
            __( 'Distance unit', 'drs-distance' ),
            array( $this, 'render_distance_unit_field' ),
            'drs_distance_rate_general',
            'drs_general_section',
            array( 'label_for' => 'drs_distance_unit' )
        );

        // Rules tab.
        add_settings_section(
            'drs_rules_section',
            __( 'Rules', 'drs-distance' ),
            array( $this, 'render_rules_section' ),
            'drs_distance_rate_rules'
        );

        add_settings_field(
            'drs_rules_editor',
            __( 'Rule matrix', 'drs-distance' ),
            array( $this, 'render_rules_field' ),
            'drs_distance_rate_rules',
            'drs_rules_section'
        );

        // Origins tab.
        add_settings_section(
            'drs_origins_section',
            __( 'Origins', 'drs-distance' ),
            array( $this, 'render_origins_section' ),
            'drs_distance_rate_origins'
        );

        add_settings_field(
            'drs_origins_editor',
            __( 'Origin locations', 'drs-distance' ),
            array( $this, 'render_origins_field' ),
            'drs_distance_rate_origins',
            'drs_origins_section'
        );

        // Advanced tab.
        add_settings_section(
            'drs_advanced_section',
            __( 'Advanced options', 'drs-distance' ),
            array( $this, 'render_advanced_section' ),
            'drs_distance_rate_advanced'
        );

        add_settings_field(
            'drs_cache_enabled',
            __( 'Enable caching', 'drs-distance' ),
            array( $this, 'render_cache_enabled_field' ),
            'drs_distance_rate_advanced',
            'drs_advanced_section',
            array( 'label_for' => 'drs_cache_enabled' )
        );

        add_settings_field(
            'drs_cache_ttl',
            __( 'Cache duration (minutes)', 'drs-distance' ),
            array( $this, 'render_cache_ttl_field' ),
            'drs_distance_rate_advanced',
            'drs_advanced_section',
            array( 'label_for' => 'drs_cache_ttl' )
        );

        add_settings_field(
            'drs_api_key',
            __( 'Distance API key', 'drs-distance' ),
            array( $this, 'render_api_key_field' ),
            'drs_distance_rate_advanced',
            'drs_advanced_section',
            array( 'label_for' => 'drs_api_key' )
        );

        add_settings_field(
            'drs_debug_mode',
            __( 'Debug logging', 'drs-distance' ),
            array( $this, 'render_debug_mode_field' ),
            'drs_distance_rate_advanced',
            'drs_advanced_section',
            array( 'label_for' => 'drs_debug_mode' )
        );
    }

    /**
     * Enqueue admin scripts and styles when viewing the settings page.
     */
    public function enqueue_assets( string $hook_suffix ): void {
        if ( $this->page_hook !== $hook_suffix ) {
            return;
        }

        $script_handle = 'drs-distance-rate-admin';
        $style_handle  = 'drs-distance-rate-admin';

        $script_file = dirname( $this->plugin_file ) . '/assets/admin.js';
        $style_file  = dirname( $this->plugin_file ) . '/assets/admin.css';

        $script_version = file_exists( $script_file ) ? (string) filemtime( $script_file ) : '1.0.0';
        $style_version  = file_exists( $style_file ) ? (string) filemtime( $style_file ) : '1.0.0';

        wp_enqueue_style(
            $style_handle,
            plugins_url( 'assets/admin.css', $this->plugin_file ),
            array(),
            $style_version
        );

        wp_enqueue_script(
            $script_handle,
            plugins_url( 'assets/admin.js', $this->plugin_file ),
            array(),
            $script_version,
            true
        );

        $settings = $this->get_settings();

        wp_localize_script(
            $script_handle,
            'drsAdmin',
            array(
                'rules'          => $settings['rules'],
                'origins'        => $settings['origins'],
                'nonce'          => wp_create_nonce( 'wp_rest' ),
                'restUrl'        => esc_url_raw( rest_url( 'drs/v1/quote' ) ),
                'currencySymbol' => Settings::get_currency_symbol(),
                'distanceUnit'   => $settings['distance_unit'],
                'i18n'           => array(
                    'noRules'        => __( 'No rules yet. Add your first rule to get started.', 'drs-distance' ),
                    'noOrigins'      => __( 'No origins configured yet.', 'drs-distance' ),
                    'deleteRule'     => __( 'Delete rule', 'drs-distance' ),
                    'deleteOrigin'   => __( 'Delete origin', 'drs-distance' ),
                    'calculatorError'=> __( 'Unable to retrieve a quote. Please review the input values and try again.', 'drs-distance' ),
                    'calculatorRule' => __( 'Applied rule', 'drs-distance' ),
                    'calculatorNone' => __( 'No matching rule was found. The fallback rate has been used.', 'drs-distance' ),
                    'calculatorTitle'=> __( 'Estimated shipping cost', 'drs-distance' ),
                ),
            )
        );
    }

    /**
     * Render the settings page with tab navigation.
     */
    public function render_page(): void {
        $tabs = array(
            'general'  => __( 'General', 'drs-distance' ),
            'rules'    => __( 'Rules', 'drs-distance' ),
            'origins'  => __( 'Origins', 'drs-distance' ),
            'advanced' => __( 'Advanced', 'drs-distance' ),
        );

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification

        if ( ! isset( $tabs[ $active_tab ] ) ) {
            $active_tab = 'general';
        }

        ?>
        <div class="wrap drs-distance-rate">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Distance Rate Shipping', 'drs-distance' ); ?></h1>
            <p class="description drs-distance-rate__intro"><?php esc_html_e( 'Configure distance-based rules, origins, and integrations for the Distance Rate shipping method.', 'drs-distance' ); ?></p>

            <nav class="nav-tab-wrapper">
                <?php foreach ( $tabs as $tab => $label ) :
                    $url = add_query_arg(
                        array(
                            'page' => 'drs-distance-rate',
                            'tab'  => $tab,
                        ),
                        admin_url( 'admin.php' )
                    );
                    ?>
                    <a class="nav-tab <?php echo $active_tab === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form action="options.php" method="post" class="drs-settings-form" data-drs-tab="<?php echo esc_attr( $active_tab ); ?>">
                <?php
                settings_fields( 'drs_distance_rate_settings_group' );
                do_settings_sections( 'drs_distance_rate_' . $active_tab );
                ?>
                <input type="hidden" name="drs_current_tab" value="<?php echo esc_attr( $active_tab ); ?>">
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Section introduction for general tab.
     */
    public function render_general_section(): void {
        echo '<p>' . esc_html__( 'Control the default presentation and behavior for the Distance Rate shipping method.', 'drs-distance' ) . '</p>';
    }

    /**
     * Render the enable checkbox field.
     */
    public function render_enabled_field(): void {
        $settings = $this->get_settings();
        $enabled  = isset( $settings['enabled'] ) ? 'yes' === $settings['enabled'] : true;
        ?>
        <label for="drs_enabled">
            <input type="checkbox" id="drs_enabled" name="<?php echo esc_attr( $this->option_name ); ?>[enabled]" value="yes" <?php checked( $enabled ); ?>>
            <?php esc_html_e( 'Enable Distance Rate shipping for your store.', 'drs-distance' ); ?>
        </label>
        <?php
    }

    /**
     * Render the method title field.
     */
    public function render_method_title_field(): void {
        $settings = $this->get_settings();
        $value    = isset( $settings['method_title'] ) ? (string) $settings['method_title'] : '';
        ?>
        <input type="text" id="drs_method_title" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[method_title]" value="<?php echo esc_attr( $value ); ?>">
        <p class="description"><?php esc_html_e( 'Shown to customers during checkout.', 'drs-distance' ); ?></p>
        <?php
    }

    /**
     * Render the handling fee field.
     */
    public function render_handling_fee_field(): void {
        $settings = $this->get_settings();
        $value    = isset( $settings['handling_fee'] ) ? (string) $settings['handling_fee'] : '0.00';
        ?>
        <input type="number" id="drs_handling_fee" min="0" step="0.01" name="<?php echo esc_attr( $this->option_name ); ?>[handling_fee]" value="<?php echo esc_attr( $value ); ?>" class="small-text">
        <span class="description"><?php esc_html_e( 'Optional handling surcharge applied to all quotes.', 'drs-distance' ); ?></span>
        <?php
    }

    /**
     * Render the default rate field.
     */
    public function render_default_rate_field(): void {
        $settings = $this->get_settings();
        $value    = isset( $settings['default_rate'] ) ? (string) $settings['default_rate'] : '0.00';
        ?>
        <input type="number" id="drs_default_rate" min="0" step="0.01" name="<?php echo esc_attr( $this->option_name ); ?>[default_rate]" value="<?php echo esc_attr( $value ); ?>" class="small-text">
        <span class="description"><?php esc_html_e( 'Used when no rule matches the package.', 'drs-distance' ); ?></span>
        <?php
    }

    /**
     * Render the backup flat-rate toggle.
     */
    public function render_fallback_enabled_field(): void {
        $settings = $this->get_settings();
        $enabled  = isset( $settings['fallback_enabled'] ) ? (string) $settings['fallback_enabled'] : 'no';
        ?>
        <label for="drs_fallback_enabled">
            <input type="checkbox" id="drs_fallback_enabled" name="<?php echo esc_attr( $this->option_name ); ?>[fallback_enabled]" value="yes" <?php checked( 'yes', $enabled ); ?>>
            <?php esc_html_e( 'Offer a predefined rate when distance providers are unavailable.', 'drs-distance' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'When checked, the backup rate below will be shown if no distance can be calculated.', 'drs-distance' ); ?></p>
        <?php
    }

    /**
     * Render the backup label field.
     */
    public function render_fallback_label_field(): void {
        $settings = $this->get_settings();
        $value    = isset( $settings['fallback_label'] ) ? (string) $settings['fallback_label'] : '';
        ?>
        <input type="text" id="drs_fallback_label" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[fallback_label]" value="<?php echo esc_attr( $value ); ?>">
        <p class="description"><?php esc_html_e( 'Optional label used when the backup rate is returned.', 'drs-distance' ); ?></p>
        <?php
    }

    /**
     * Render the backup cost field.
     */
    public function render_fallback_cost_field(): void {
        $settings = $this->get_settings();
        $value    = isset( $settings['fallback_cost'] ) ? (string) $settings['fallback_cost'] : '0.00';
        ?>
        <input type="number" id="drs_fallback_cost" min="0" step="0.01" name="<?php echo esc_attr( $this->option_name ); ?>[fallback_cost]" value="<?php echo esc_attr( $value ); ?>" class="small-text">
        <span class="description"><?php esc_html_e( 'Flat cost applied when the backup rate is used.', 'drs-distance' ); ?></span>
        <?php
    }

    /**
     * Render the distance unit selector.
     */
    public function render_distance_unit_field(): void {
        $settings = $this->get_settings();
        $value    = isset( $settings['distance_unit'] ) ? (string) $settings['distance_unit'] : 'km';
        ?>
        <select id="drs_distance_unit" name="<?php echo esc_attr( $this->option_name ); ?>[distance_unit]">
            <option value="km" <?php selected( 'km', $value ); ?>><?php esc_html_e( 'Kilometers', 'drs-distance' ); ?></option>
            <option value="mi" <?php selected( 'mi', $value ); ?>><?php esc_html_e( 'Miles', 'drs-distance' ); ?></option>
        </select>
        <p class="description"><?php esc_html_e( 'Determines the unit used when evaluating distances and displaying inputs.', 'drs-distance' ); ?></p>
        <?php
    }

    /**
     * Section introduction for the rules tab.
     */
    public function render_rules_section(): void {
        echo '<p>' . esc_html__( 'Create distance-based pricing tiers. Rules are evaluated in order until a match is found.', 'drs-distance' ) . '</p>';
    }

    /**
     * Render the editable rule matrix.
     */
    public function render_rules_field(): void {
        $settings   = $this->get_settings();
        $rules      = $settings['rules'];
        $rules_json = wp_json_encode( $rules );

        if ( ! is_string( $rules_json ) ) {
            $rules_json = '[]';
        }
        ?>
        <table class="widefat drs-table" id="drs-rules-table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Label', 'drs-distance' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Min distance', 'drs-distance' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Max distance', 'drs-distance' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Base cost', 'drs-distance' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Cost per distance', 'drs-distance' ); ?></th>
                    <th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'drs-distance' ); ?></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <p id="drs-no-rules" class="drs-empty-state"></p>
        <p>
            <button type="button" class="button" id="drs-add-rule"><?php esc_html_e( 'Add rule', 'drs-distance' ); ?></button>
        </p>
        <input type="hidden" id="drs_rules_json" name="<?php echo esc_attr( $this->option_name ); ?>[rules]" value="<?php echo esc_attr( $rules_json ); ?>">
        <?php $this->render_calculator(); ?>
        <?php
    }

    /**
     * Section introduction for the origins tab.
     */
    public function render_origins_section(): void {
        echo '<p>' . esc_html__( 'Manage the list of warehouses or pickup locations that can be used when determining distances.', 'drs-distance' ) . '</p>';
    }

    /**
     * Render the origin editor table.
     */
    public function render_origins_field(): void {
        $settings     = $this->get_settings();
        $origins      = $settings['origins'];
        $origins_json = wp_json_encode( $origins );

        if ( ! is_string( $origins_json ) ) {
            $origins_json = '[]';
        }
        ?>
        <table class="widefat drs-table" id="drs-origins-table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Label', 'drs-distance' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Address', 'drs-distance' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Postcode', 'drs-distance' ); ?></th>
                    <th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'drs-distance' ); ?></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <p id="drs-no-origins" class="drs-empty-state"></p>
        <p>
            <button type="button" class="button" id="drs-add-origin"><?php esc_html_e( 'Add origin', 'drs-distance' ); ?></button>
        </p>
        <input type="hidden" id="drs_origins_json" name="<?php echo esc_attr( $this->option_name ); ?>[origins]" value="<?php echo esc_attr( $origins_json ); ?>">
        <?php
    }

    /**
     * Section introduction for advanced settings.
     */
    public function render_advanced_section(): void {
        echo '<p>' . esc_html__( 'Fine-tune integrations and diagnostics.', 'drs-distance' ) . '</p>';
    }

    /**
     * Render the cache toggle field.
     */
    public function render_cache_enabled_field(): void {
        $settings = $this->get_settings();
        $enabled  = isset( $settings['cache_enabled'] ) ? (string) $settings['cache_enabled'] : 'yes';
        ?>
        <label for="drs_cache_enabled">
            <input type="checkbox" id="drs_cache_enabled" name="<?php echo esc_attr( $this->option_name ); ?>[cache_enabled]" value="yes" <?php checked( 'yes', $enabled ); ?>>
            <?php esc_html_e( 'Store geocode and distance results for faster calculations.', 'drs-distance' ); ?>
        </label>
        <?php
    }

    /**
     * Render the cache TTL field.
     */
    public function render_cache_ttl_field(): void {
        $settings = $this->get_settings();
        $value    = isset( $settings['cache_ttl'] ) ? (int) $settings['cache_ttl'] : 30;
        ?>
        <input type="number" id="drs_cache_ttl" min="0" step="1" name="<?php echo esc_attr( $this->option_name ); ?>[cache_ttl]" value="<?php echo esc_attr( $value ); ?>" class="small-text">
        <span class="description"><?php esc_html_e( 'Cache lifetime in minutes. Use 0 to disable expiration.', 'drs-distance' ); ?></span>
        <?php
    }

    /**
     * Render API key field.
     */
    public function render_api_key_field(): void {
        $settings = $this->get_settings();
        $value    = isset( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
        ?>
        <input type="text" id="drs_api_key" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[api_key]" value="<?php echo esc_attr( $value ); ?>">
        <p class="description"><?php esc_html_e( 'Optional key for third-party distance services (e.g. Google Maps, Mapbox).', 'drs-distance' ); ?></p>
        <?php
    }

    /**
     * Render debug mode toggle.
     */
    public function render_debug_mode_field(): void {
        $settings = $this->get_settings();
        $enabled  = isset( $settings['debug_mode'] ) ? 'yes' === $settings['debug_mode'] : false;
        ?>
        <label for="drs_debug_mode">
            <input type="checkbox" id="drs_debug_mode" name="<?php echo esc_attr( $this->option_name ); ?>[debug_mode]" value="yes" <?php checked( $enabled ); ?>>
            <?php esc_html_e( 'Write detailed logs when calculating quotes.', 'drs-distance' ); ?>
        </label>
        <?php
    }

    /**
     * Render the inline calculator UI.
     */
    private function render_calculator(): void {
        $settings      = $this->get_settings();
        $distance_unit = isset( $settings['distance_unit'] ) ? (string) $settings['distance_unit'] : 'km';
        $unit_label    = 'mi' === $distance_unit ? __( 'mi', 'drs-distance' ) : __( 'km', 'drs-distance' );
        ?>
        <div id="drs-calculator" class="drs-calculator">
            <h3><?php esc_html_e( 'Test calculator', 'drs-distance' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Preview how the current configuration responds to a sample package. Results use the stored rules and fallback settings.', 'drs-distance' ); ?></p>
            <div class="drs-calculator__grid">
                <label for="drs-calculator-origin">
                    <?php esc_html_e( 'Origin postcode', 'drs-distance' ); ?>
                    <input type="text" id="drs-calculator-origin" autocomplete="postal-code">
                </label>
                <label for="drs-calculator-destination">
                    <?php esc_html_e( 'Destination postcode', 'drs-distance' ); ?>
                    <input type="text" id="drs-calculator-destination" autocomplete="postal-code">
                </label>
                <label for="drs-calculator-distance">
                    <span><?php echo esc_html( sprintf( __( 'Distance (%s)', 'drs-distance' ), $unit_label ) ); ?></span>
                    <input type="number" min="0" step="0.01" id="drs-calculator-distance">
                </label>
                <label for="drs-calculator-weight">
                    <?php esc_html_e( 'Weight (kg)', 'drs-distance' ); ?>
                    <input type="number" min="0" step="0.01" id="drs-calculator-weight">
                </label>
                <label for="drs-calculator-items">
                    <?php esc_html_e( 'Items', 'drs-distance' ); ?>
                    <input type="number" min="0" step="1" id="drs-calculator-items">
                </label>
                <label for="drs-calculator-subtotal">
                    <?php esc_html_e( 'Order subtotal', 'drs-distance' ); ?>
                    <input type="number" min="0" step="0.01" id="drs-calculator-subtotal">
                </label>
            </div>
            <p class="drs-calculator__actions">
                <button type="button" class="button button-primary" id="drs-run-calculation"><?php esc_html_e( 'Calculate quote', 'drs-distance' ); ?></button>
                <span class="spinner" id="drs-calculator-spinner"></span>
            </p>
            <div id="drs-calculator-result" class="drs-calculator__result" aria-live="polite"></div>
        </div>
        <?php
    }

    /**
     * Sanitize option values before persisting them.
     *
     * @param mixed $input Raw input from the request.
     * @return array<string, mixed>
     */
    public function sanitize_settings( $input ): array {
        if ( ! is_array( $input ) ) {
            $input = array();
        }

        $current   = $this->get_settings();
        $tab       = isset( $_POST['drs_current_tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['drs_current_tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
        $input     = wp_unslash( $input );

        switch ( $tab ) {
            case 'general':
                $current['enabled']       = isset( $input['enabled'] ) && 'yes' === $input['enabled'] ? 'yes' : 'no';
                $current['method_title']  = isset( $input['method_title'] ) ? sanitize_text_field( $input['method_title'] ) : '';
                $current['handling_fee']  = isset( $input['handling_fee'] ) ? $this->sanitize_amount( $input['handling_fee'] ) : '0.00';
                $current['default_rate']  = isset( $input['default_rate'] ) ? $this->sanitize_amount( $input['default_rate'] ) : '0.00';
                $unit                     = isset( $input['distance_unit'] ) ? sanitize_text_field( $input['distance_unit'] ) : 'km';
                $current['distance_unit'] = in_array( $unit, array( 'km', 'mi' ), true ) ? $unit : 'km';
                $current['fallback_enabled'] = isset( $input['fallback_enabled'] ) && 'yes' === $input['fallback_enabled'] ? 'yes' : 'no';
                $current['fallback_label']   = isset( $input['fallback_label'] ) ? sanitize_text_field( $input['fallback_label'] ) : '';
                $current['fallback_cost']    = isset( $input['fallback_cost'] ) ? $this->sanitize_amount( $input['fallback_cost'] ) : '0.00';
                break;

            case 'rules':
                $current['rules'] = $this->sanitize_rules( $input['rules'] ?? array() );
                break;

            case 'origins':
                $current['origins'] = $this->sanitize_origins( $input['origins'] ?? array() );
                break;

            case 'advanced':
                $current['api_key']    = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
                $current['debug_mode'] = ! empty( $input['debug_mode'] ) && 'yes' === $input['debug_mode'] ? 'yes' : 'no';
                $current['cache_enabled'] = ! empty( $input['cache_enabled'] ) && 'yes' === $input['cache_enabled'] ? 'yes' : 'no';
                $ttl = isset( $input['cache_ttl'] ) ? (int) $input['cache_ttl'] : ( $current['cache_ttl'] ?? 30 );
                $current['cache_ttl'] = max( 0, (int) $ttl );
                break;
        }

        $this->settings_cache = $current;

        return $current;
    }

    /**
     * Fetch settings with caching.
     *
     * @return array<string, mixed>
     */
    private function get_settings(): array {
        if ( null !== $this->settings_cache ) {
            return $this->settings_cache;
        }

        $settings = Settings::get_settings();
        $this->settings_cache = $settings;

        return $settings;
    }

    /**
     * Sanitize the rule editor payload.
     *
     * @param mixed $raw_rules Raw rules array or JSON string.
     * @return array<int, array<string, string>>
     */
    private function sanitize_rules( $raw_rules ): array {
        if ( is_string( $raw_rules ) ) {
            $decoded   = json_decode( wp_unslash( $raw_rules ), true );
            $raw_rules = is_array( $decoded ) ? $decoded : array();
        }

        if ( ! is_array( $raw_rules ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $raw_rules as $rule ) {
            if ( ! is_array( $rule ) ) {
                continue;
            }

            $id = isset( $rule['id'] ) ? sanitize_key( (string) $rule['id'] ) : '';

            if ( '' === $id ) {
                $id = sanitize_key( uniqid( 'rule_', true ) );
            }

            $label             = isset( $rule['label'] ) ? sanitize_text_field( wp_unslash( (string) $rule['label'] ) ) : '';
            $min_distance      = isset( $rule['min_distance'] ) ? $this->sanitize_amount( $rule['min_distance'] ) : '0.00';
            $max_distance_raw  = $rule['max_distance'] ?? '';
            $max_distance      = '' === $max_distance_raw || null === $max_distance_raw ? '' : $this->sanitize_amount( $max_distance_raw );
            $base_cost         = isset( $rule['base_cost'] ) ? $this->sanitize_amount( $rule['base_cost'] ) : '0.00';
            $cost_per_distance = isset( $rule['cost_per_distance'] ) ? $this->sanitize_amount( $rule['cost_per_distance'] ) : '0.00';

            $sanitized[] = array(
                'id'                => $id,
                'label'             => $label,
                'min_distance'      => $min_distance,
                'max_distance'      => $max_distance,
                'base_cost'         => $base_cost,
                'cost_per_distance' => $cost_per_distance,
            );
        }

        return $sanitized;
    }

    /**
     * Sanitize the origin payload.
     *
     * @param mixed $raw_origins Raw origin values or JSON string.
     * @return array<int, array<string, string>>
     */
    private function sanitize_origins( $raw_origins ): array {
        if ( is_string( $raw_origins ) ) {
            $decoded     = json_decode( wp_unslash( $raw_origins ), true );
            $raw_origins = is_array( $decoded ) ? $decoded : array();
        }

        if ( ! is_array( $raw_origins ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $raw_origins as $origin ) {
            if ( ! is_array( $origin ) ) {
                continue;
            }

            $id = isset( $origin['id'] ) ? sanitize_key( (string) $origin['id'] ) : '';

            if ( '' === $id ) {
                $id = sanitize_key( uniqid( 'origin_', true ) );
            }

            $label    = isset( $origin['label'] ) ? sanitize_text_field( wp_unslash( (string) $origin['label'] ) ) : '';
            $address  = isset( $origin['address'] ) ? sanitize_text_field( wp_unslash( (string) $origin['address'] ) ) : '';
            $postcode = isset( $origin['postcode'] ) ? sanitize_text_field( wp_unslash( (string) $origin['postcode'] ) ) : '';

            $sanitized[] = array(
                'id'       => $id,
                'label'    => $label,
                'address'  => $address,
                'postcode' => $postcode,
            );
        }

        return $sanitized;
    }

    /**
     * Normalize a numeric field into a decimal string.
     *
     * @param mixed $value Raw numeric value.
     */
    private function sanitize_amount( $value ): string {
        if ( is_array( $value ) ) {
            $value = implode( '', $value );
        }

        return Settings::format_decimal( $value );
    }
}
