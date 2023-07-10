<?php
/*
Plugin Name: Weather Station
Plugin URI: https://www.roict.nl
Description: This plugin reads data from a weather station.
Version: 1.1
Author: Rory Meijer
Author URI: https://www.roict.nl
*/


// Register the private Weather Station shortcode
add_shortcode( 'private_weather', 'private_weather_shortcode' );

// Register the activation hook
register_activation_hook( __FILE__, 'private_weathergenerate_guid' );

// Function to generate and store the GUID

	
function private_weathergenerate_guid() {
	 update_option( 'herhalen', '24' );
		
	update_option( 'private_latitude', '52.3547417' );
        update_option( 'private_longitude', '4.8213895' );
    $guid = get_option( 'private_weather_guid' );

    if ( empty( $guid ) ) {
        $guid = wp_generate_uuid4();
        update_option( 'private_weather_guid', $guid );
    }
    global $wpdb;
       

    $table_name = $wpdb->prefix . 'private_weather_data';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        timestamp DATETIME NOT NULL,
        temperature DECIMAL(5,2) NOT NULL,
        humidity DECIMAL(5,2) NOT NULL,
        weather VARCHAR(100) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

}


  function private_weather_shortcode($atts) {
	    
    $cached_data = private_weather_get_cached_data();

    if ( $cached_data ) {
        $temperature = $cached_data->temperature;
        $humidity = $cached_data->humidity;
        $weather = $cached_data->weather;
        $timestamp = $cached_data->timestamp;
    } else {
        // Haal de gegevens op van de private API en sla ze op in de database
        private_weather_fetch_and_save_data();

        // Haal de gegevens opnieuw op uit de database
        $cached_data = private_weather_get_cached_data();

        if ( ! $cached_data ) {
            return 'Geen weerdata beschikbaar.';
        }

        $temperature = $cached_data->temperature;
        $humidity = $cached_data->humidity;
        $weather = $cached_data->weather;
        $timestamp = $cached_data->timestamp;
    }

    // Verwerk de attributen en toon de weerdata

    $show_temperature = isset( $atts['temperature'] ) && $atts['temperature'] === 'true';
    $show_humidity = isset( $atts['humidity'] ) && $atts['humidity'] === 'true';
    $show_weather = isset( $atts['weather'] ) && $atts['weather'] === 'true';

   
   $html = '';

    $client_id = get_option( 'private_client_id' );
	
	if(empty($client_id)){
    if ( $show_temperature  || ! isset( $atts['temperature'] )) {
        $html .= '<p>Temperature: ' . $temperature . ' °C</p>';
    }
	}else{
		
    if ( $show_temperature  || ! isset( $atts['temperature'] )) {
        $html .= '<p>Temperature: ' . $temperature . ' °C</p>';
    }

    if ( $show_humidity  || ! isset( $atts['humidity'] )) {
        $html .= '<p>Humidity: ' . $humidity . ' %</p>';
    }

    if ( $show_weather  || ! isset( $atts['weather'] )) {
        $html .= '<p>Weather: ' . $weather . '</p>';
    }
	}
    $html .= '<p>Timestamp: ' . $timestamp . '</p>';

    return $html;
}



function private_weather_save_data( $data ) {
    global $wpdb;
	//var_dump($data);
	
    $table_name = $wpdb->prefix . 'private_weather_data';

	$last_record = $wpdb->get_row( "SELECT * FROM $table_name ORDER BY id DESC LIMIT 1" );
    if ( $last_record && strtotime( $last_record->timestamp ) > strtotime( '-'.get_option( 'herhalen' ).' hour' ) ) {
        return; // Skip inserting a new record
    }
	if(is_array($data)){
		$temperature = $data['Temperature']; 
	}else{
    $temperature = $data->dashboard_data->Temperature;
	}	
	if(is_array($data)){
		$humidity = $data['Humidity']; 
	}else{
    $humidity = $data->dashboard_data->Humidity;
	}
	if(is_array($data)){
		$weather = $data['Weather']; 
	}else{
    $weather = isset( $data->dashboard_data->Weather ) ? $data->dashboard_data->Weather->status : 'N/A';
	}

    $wpdb->insert(
        $table_name,
        array(
			'timestamp' => current_time( 'mysql' ),
            'temperature' => $temperature,
            'humidity' => $humidity,
            'weather' => $weather
        ),
        array(
			'%s',
            '%f',
            '%f',
            '%s'
        )
    );
}
// Add the plugin settings page
add_action( 'admin_menu', 'private_weather_settings_page' );

function private_weather_settings_page() {
   
     add_menu_page(
        'Weather Station', // Paginatitel
        'Weather', // Menutitel
        'manage_options', // Gebruikersmachtiging
        'private-weather-settings', // Slug
        'private_weather_settings', // Callback functie
        'dashicons-cloud', // Pictogram
        30 // Menu positie
    );
	$file_path = 'https://koffie.roict.nl/'.get_option("private_weather_guid").'.txt';

$headers = get_headers( $file_path );
if($headers && strpos( $headers[0], '200' ) !== false){
	$koffie = true;
}else{ $koffie = false;
}
if($koffie == true){ 
    add_submenu_page(
        'private-weather-settings', // Parent slug, moet overeenkomen met de instellingenpagina
        'Weather Records', // Paginatitel
        'Weather Records', // Menutitel
        'manage_options', // Gebruikersmachtiging
        'private-weather-records', // Slug
        'private_weather_records_callback' // Callback functie
    );
}
}

function private_weather_settings() {

		$file_path = 'https://koffie.roict.nl/'.get_option("private_weather_guid").'.txt';

$headers = get_headers( $file_path );
if($headers && strpos( $headers[0], '200' ) !== false){
	$koffie = true;
}else{ $koffie = false;
}
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['submit'] ) ) {  
	update_option( 'private_latitude', $_POST['private_latitude'] );
        update_option( 'private_longitude', $_POST['private_longitude'] );

        update_option( 'private_client_id', $_POST['private_client_id'] );
        update_option( 'private_client_secret', $_POST['private_client_secret'] );
        update_option( 'private_username', $_POST['private_username'] );
        update_option( 'private_password', $_POST['private_password' ] );
        update_option( 'herhalen', $_POST['herhalen' ] );
		//update_option( 'private_cron_schedule', $_POST['private_cron_schedule'] );
  // Update the cron schedule if it has changed
  $new_cron_schedule = $_POST['private_cron_schedule'];
    $current_cron_schedule = get_option( 'private_cron_schedule', 'hourly' );

    if ( $new_cron_schedule !== $current_cron_schedule ) {
        update_option( 'private_cron_schedule', $new_cron_schedule );

        // Clear the existing scheduled cron event and schedule the new one
        private_weather_schedule_hourly_cron();
    }
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    $client_id = get_option( 'private_client_id' );
    $client_secret = get_option( 'private_client_secret' );
    $username = get_option( 'private_username' );
    $password = get_option( 'private_password' );
$herhalen = get_option( 'refresh', '24' );
    $latitude = get_option( 'private_latitude');
    $longitude = get_option( 'private_longitude');

    ?>
    <div class="wrap">
        <h1>Weather Station Settings</h1>
        <form method="post">
            <table class="form-table">
			   <tr>
                    <th scope="row"><label for="private_latitude">Latitude</label></th>
                    <td><input type="text" name="private_latitude" id="private_latitude" value="<?php echo esc_attr( $latitude ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="private_longitude">Longitude</label></th>
                    <td><input type="text" name="private_longitude" id="private_longitude" value="<?php echo esc_attr( $longitude ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="private_client_id">Client ID</label></th>
                    <td><input type="text" name="private_client_id" id="private_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="private_client_secret">Client Secret</label></th>
                    <td><input type="password" name="private_client_secret" id="private_client_secret" value="<?php echo esc_attr( $client_secret ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="private_username">Username</label></th>
                    <td><input type="text" name="private_username" id="private_username" value="<?php echo esc_attr( $username ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="private_password">Password</label></th>
                    <td><input type="password" name="private_password" id="private_password" value="<?php echo esc_attr( $password ); ?>" class="regular-text" /></td>
                </tr>
				   <?php if($koffie == true){ ?><tr>
                 <th scope="row"><label for="private_cron_schedule">Cron Schedule</label></th>
                    <td>
                        <select name="private_cron_schedule" id="private_cron_schedule">
                            <option value="hourly" <?php selected( $cron_schedule, 'hourly' ); ?>>Hourly</option>
                            <option value="twicedaily" <?php selected( $cron_schedule, 'twicedaily' ); ?>>Twice Daily</option>
                            <option value="daily" <?php selected( $cron_schedule, 'daily' ); ?>>Daily</option>
                        </select>
                    </td>
                </tr>
				   <?php } ?>
				   <?php if($koffie == true){ ?><tr>
                 <th scope="row"><label for="herhalen">Gegevens herladen na</label></th>
                    <td>
                        <select name="herhalen" id="herhalen">
                            <option value="1" <?php selected( $herhalen, '1' ); ?>>1 uur</option>
                            <option value="2" <?php selected( $herhalen, '2' ); ?>>2 uur</option>
                            <option value="3" <?php selected( $herhalen, '3' ); ?>>3 uur</option>
                            <option value="4" <?php selected( $herhalen, '4' ); ?>>3 uur</option>
                            <option value="5" <?php selected( $herhalen, '5' ); ?>>3 uur</option>
                            <option value="6" <?php selected( $herhalen, '6' ); ?>>3 uur</option>
                            <option value="7" <?php selected( $herhalen, '7' ); ?>>7 uur</option>
                            <option value="8" <?php selected( $herhalen, '8' ); ?>>8 uur</option>
                            <option value="9" <?php selected( $herhalen, '9' ); ?>>9 uur</option>
                            <option value="10" <?php selected( $herhalen, '10' ); ?>>10 uur</option>
                            <option value="11" <?php selected( $herhalen, '11' ); ?>>11 uur</option>
                            <option value="12" <?php selected( $herhalen, '12' ); ?>>12 uur</option>
                            <option value="13" <?php selected( $herhalen, '13' ); ?>>13 uur</option>
                            <option value="14" <?php selected( $herhalen, '14' ); ?>>14 uur</option>
                            <option value="15" <?php selected( $herhalen, '15' ); ?>>15 uur</option>
                            <option value="16" <?php selected( $herhalen, '16' ); ?>>16 uur</option>
                            <option value="17" <?php selected( $herhalen, '17' ); ?>>17 uur</option>
                            <option value="18" <?php selected( $herhalen, '18' ); ?>>18 uur</option>
                            <option value="19" <?php selected( $herhalen, '19' ); ?>>19 uur</option>
                            <option value="20" <?php selected( $herhalen, '20' ); ?>>20 uur</option>
                            <option value="21" <?php selected( $herhalen, '21' ); ?>>21 uur</option>
                            <option value="22" <?php selected( $herhalen, '22' ); ?>>22 uur</option>
                            <option value="23" <?php selected( $herhalen, '23' ); ?>>23 uur</option>
                            <option value="24" <?php selected( $herhalen, '24' ); ?>>24 uur</option>
                        </select>
                    </td>
                </tr> <tr>
                    <th scope="row"><label for="private_data_retention">Automatisch verwijderen na (dagen)</label></th>
                    <td><input type="number" name="private_data_retention" id="private_data_retention" value="<?php echo esc_attr( get_option( 'private_data_retention', 30 ) ); ?>" class="regular-text" /></td>
                </tr>
				   <?php } ?>
            </table>
            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings" /></p>
        </form>
		<?php

if ( $koffie == true ) {
    // The file exists
   echo"Bedankt voor het kopje koffie, als bedankje heb ik de extra functionaliteit van deze plugin geactiveerd!";
} else {
    // The file doesn't exist
    
?><p>Als je deze plugin waardeert, overweeg dan een kleine donatie voor een kopje koffie:</p>
<p><a href="https://bunq.me/kopjekoffie/Koffie van <?php echo get_option("private_weather_guid"); ?>/" target="_blank" style='text-decoration: none; font-size: 15px;'> <i class="dashicons dashicons-coffee" style=' font-size: 15px;'></i> Doneer een kopje koffie</a></p>
<b>Als je de GUID in het betaalverzoek laat staan, dan kan ik extra opties voor je activeren!</b>
<?php } ?>	<!-- Handleiding -->
        <h1>Weather Station Settings</h1>
        <h2>Handleiding</h2>
        <ol>
            <li>Plugin installeren:
                <ul>
                    <li>Download de Weather Station-plugin van de WordPress Plugin Directory of zoek naar "Weather Station" in het "Plugins" gedeelte van je WordPress-dashboard.</li>
                    <li>Klik op "Installeren" en vervolgens op "Activeren" om de plugin te activeren.</li>
                </ul>
            </li>
            <li>Netatmo API-gegevens configureren:
                <ul>
                    <li>Ga naar het "Weather Station" submenu-item onder "Instellingen" in je WordPress-dashboard.</li>
                    <li>Voer je Netatmo API-clientgegevens in. Je kunt deze verkrijgen door een Netatmo-ontwikkelaarsaccount aan te maken op de Netatmo Developer website.</li>
					<li>Als je geen client_id / client_secret invult, maakt de plugin verbinding met de OpenMateo API, maar in dat geval zijn je Latitude en Longitude verplicht.</li>
                    <li>Voer ook je Netatmo gebruikersnaam en wachtwoord in.</li>
                    <li>Klik op "Instellingen opslaan" om de wijzigingen op te slaan.</li>
                </ul>
            </li>
            <li>Weerstationgegevens weergeven op een pagina:
                <ul>
                    <li>Ga naar de pagina waar je de weerstationgegevens wilt weergeven of maak een nieuwe pagina aan.</li>
                    <li>Voeg de volgende shortcode toe aan de inhoud van de pagina: <code>[private_weather]</code>.</li>
                    <li>Je kunt ook gebruikmaken van attributen in de shortcode om specifieke informatie weer te geven. Standaard worden alle beschikbare gegevens weergegeven. Voorbeeld: <code>[private_weather temperature="false" weather="true"]</code> om alleen de weersinformatie weer te geven zonder de temperatuur.</li>
                    <li>Publiceer of werk de pagina bij en bekijk de pagina. Je zou nu de weerstationgegevens moeten zien.</li>
                </ul>
            </li>
        </ol>
    </div>
    <?php
}
function private_weather_delete_old_records() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'private_weather_data';
    // Haal het aantal dagen op uit de instellingen
    $data_retention_days = get_option( 'private_data_retention', 30 );

    // Calculate the date 30 days ago
    $older_than_date = date( 'Y-m-d', strtotime( '-' . $data_retention_days . ' days' ) );

    // Prepare the SQL query to delete records older than 30 days
    $query = $wpdb->prepare( "DELETE FROM $table_name WHERE timestamp < %s", $older_than_date );

    // Execute the query
    $wpdb->query( $query );
}
function private_weather_schedule_records_deletion() {
    if ( ! wp_next_scheduled( 'private_weather_delete_old_records_event' ) ) {
        wp_schedule_event( time(), 'daily', 'private_weather_delete_old_records_event' );
    }
}
register_activation_hook( __FILE__, 'private_weather_schedule_records_deletion' );
add_action( 'private_weather_delete_old_records_event', 'private_weather_delete_old_records' );


function private_weather_fetch_from_open_meteo() {
    //$api_key = 'YOUR_OPEN_METEO_API_KEY'; // Replace with your actual API key from open-meteo.com
   // $location = 'YOUR_LOCATION'; // Replace with the location for which you want to fetch weather data
  

    $latitude = get_option( 'private_latitude' );
    $longitude = get_option( 'private_longitude' );

    // Construct the API request URL
    $api_url = "https://api.open-meteo.com/v1/forecast?current_weather=true&latitude=".$latitude."&longitude=".$longitude."";
    // Perform the API request
    $response = wp_remote_get( $api_url );

    if ( is_wp_error( $response ) ) {
        return false; // Return false on API request error
    }

    $body = wp_remote_retrieve_body( $response );
	//print_r($body);
    $data = json_decode( $body, true );
	//echo $data['current_weather']['temperature'];
    if ( ! isset(  $data['current_weather']) ) {
        return false; // Return false if weather data is not available in the response
    }

    // Extract the relevant weather information
    $temperature = $data['current_weather']['temperature'];
    $humidity = '0';
    $weather = '0';

    // Format the weather data as an array
    $dashboard_data = array(
        'Temperature' => $temperature,
        'Humidity' => $humidity,
        'Weather' => $weather,
    );

    return $dashboard_data;
}

//Premium functionaliteit
function private_weather_fetch_and_save_data() {
 
    // Retrieve the API credentials from the plugin settings
    $client_id = get_option( 'private_client_id' );
    $client_secret = get_option( 'private_client_secret' );
    $username = get_option( 'private_username' );
    $password = get_option( 'private_password' );


    if ( empty( $client_id ) ) {
        // Fetch weather data from open-meteo.com
        $weather_data = private_weather_fetch_from_open_meteo();
//echo $weather_data;
        if ( ! $weather_data ) {
            return 'Error retrieving weather data from open-meteo.com.';
        }else{
			        private_weather_save_data( $weather_data );
		}
	}else{
    // Authenticate with the Netatmo API
    $auth_url = 'https://api.netatmo.com/oauth2/token';
    $auth_params = array(
        'grant_type' => 'password',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'username' => $username,
        'password' => $password,
        'scope' => 'read_station',
    );

    $response = wp_remote_post( $auth_url, array( 'body' => $auth_params ) );

    if ( is_wp_error( $response ) ) {
        return 'Error connecting to the Netatmo API.';
    }

    $auth_data = json_decode( wp_remote_retrieve_body( $response ) );

    // Retrieve the weather station data
    $weather_url = 'https://api.netatmo.com/api/getstationsdata';
    $weather_params = array(
        'access_token' => $auth_data->access_token,
    );

    $response = wp_remote_get( add_query_arg( $weather_params, $weather_url ) );

    if ( is_wp_error( $response ) ) {
        return 'Error retrieving weather station data.';
    }

    $weather_data = json_decode( wp_remote_retrieve_body( $response ) );

    // Parse and display the weather data
    $html = '';

    foreach ( $weather_data->body->devices as $device ) {
       
	
        private_weather_save_data( $device );


        // Add more data fields as needed
    }
	}
}

add_action( 'private_weather_hourly_cron', 'private_weather_fetch_and_save_data' );

// Schedule the cron event on plugin activation
register_activation_hook( __FILE__, 'private_weather_schedule_hourly_cron' );

// Schedule the cron event
function private_weather_schedule_hourly_cron() {
      $cron_schedule = get_option( 'private_cron_schedule', 'hourly' );

    // Clear the existing scheduled cron event
    wp_clear_scheduled_hook( 'private_weather_hourly_cron' );

    // Schedule the new cron event
    wp_schedule_event( time(), $cron_schedule, 'private_weather_hourly_cron' );

}

// Clear the scheduled cron event on plugin deactivation
register_deactivation_hook( __FILE__, 'private_weather_clear_hourly_cron' );

// Clear the scheduled cron event
function private_weather_clear_hourly_cron() {
    wp_clear_scheduled_hook( 'private_weather_hourly_cron' );
}

// Voeg de plugin admin-menuoptie toe
//add_action( 'admin_menu', 'private_weather_records_page' );

function private_weather_records_page() {
   /* add_submenu_page(
        'private-weather-settings', // Parent slug, moet overeenkomen met de instellingenpagina
        'Weather Records', // Paginatitel
        'Weather Records', // Menutitel
        'manage_options', // Gebruikersmachtiging
        'private-weather-records', // Slug
        'private_weather_records_callback' // Callback functie
    );*/
}

// Callback functie voor de admin-pagina
function private_weather_records_callback() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'private_weather_data';

    // Verwerk het verwijderen van records als het formulier wordt ingediend
    if ( isset( $_POST['delete_records'] ) ) {
        $record_ids = isset( $_POST['record_ids'] ) ? $_POST['record_ids'] : array();

        foreach ( $record_ids as $record_id ) {
            $wpdb->delete(
                $table_name,
                array( 'id' => $record_id ),
                array( '%d' )
            );
        }
    }

    // Haal alle records op uit de tabel
    $records = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );

    // Toon de records in een tabel
    ?>
    <div class="wrap">
        <h1>Weather Records</h1>
        <form method="post">
            <table class="wp-list-table widefat fixed striped">
               <thead><tr><th><input type="checkbox" id="select-all" /></th><th>ID</th><th>Temperature</th><th>Humidity</th><th>Weather</th><th>Timestamp</th></tr></thead>
   
                <tbody>
                    <?php
                    foreach ( $records as $record ) {
                        ?>
                        <tr>
                            <td><input type="checkbox" name="record_ids[]" value="<?php echo esc_attr( $record->id ); ?>"></td>
                            <td><?php echo $record->id; ?></td>
                            <td><?php echo $record->temperature; ?></td>
                            <td><?php echo $record->humidity; ?></td>
                            <td><?php echo $record->weather; ?></td>
                            <td><?php echo $record->timestamp; ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
            <p><input type="submit" name="delete_records" class="button button-primary" value="Delete Selected Records"></p>
        </form>
    </div>
    <?php
}
function private_weather_get_cached_data() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'private_weather_data';

    $last_record = $wpdb->get_row( "SELECT * FROM $table_name ORDER BY id DESC LIMIT 1" );
    if ( $last_record && strtotime( $last_record->timestamp ) > strtotime( '-'.get_option( 'herhalen' ).' hour' ) ) {
        return $last_record;
    }

    return false;
}

function private_weather_admin_enqueue_scripts() {
    wp_enqueue_script( 'private-weather-admin-script', plugin_dir_url( __FILE__ ) . 'admin.js', array( 'jquery' ), '1.0', true );
}

add_action( 'admin_enqueue_scripts', 'private_weather_admin_enqueue_scripts' );


//Einde Premium functionaliteit