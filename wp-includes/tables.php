<?php

require_once( __DIR__ .'/../vendor/autoload.php');

//Our class extends the WP_List_Table class, so we need to make sure that it's there
if(!class_exists('WP_List_Table')){
   require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

use Illuminate\Support\Str;

class SystemStatusTable extends \WP_List_Table {

    public function __construct( $args = array() ) {
        parent::__construct( $args );
        $columns               = $this->get_columns();
        $hidden                = array();
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable, 'title' );
        $this->locking_info    = new GFFormLocking();
        $this->filter          = rgget( 'filter' );
    }

    public function get_columns() {
        return $columns = [
            'service_name'    => __('Service'),
            'is_supported'    => __('Supported'),
            'service_status'  => __('Status'),
            'service_version' => __('Version'),
        ];
    }

    public function get_sortable_columns() {
        return $sortable = [
          'service_name'    => 'service_name',
          'is_supported'    => 'is_supported',
          'service_status'  => 'service_status',
          'service_version' => 'service_version'
        ];
    }

    public function prepare_items() {
        global $wpdb, $_wp_column_headers;
        $screen = get_current_screen();

        $this->items = [];

        foreach (['Php','Python3','Supervisord','Composer','Gravity Forms'] as $service) {
            $this->items[] = [
                'service_name' => $service,
                'is_supported' => (!\App\NxvcCore::version(strtolower($service)) ? '<span style="color:red;text-align: center;">&#10007</span>' : '<span style="color:green;text-align: center;">&#10003</span>'),
                'service_status' => \App\NxvcCore::has(strtolower($service), false),
                'service_version' => \App\NxvcCore::version(strtolower($service))
            ];
        }

        $columns = $this->get_columns();
        $_wp_column_headers[$screen->id] = $columns;
    }

    /**
     * Display the rows of records in the table
     * @return string, echo the markup of the rows
     */
    function display_rows() {

       //Get the records registered in the prepare_items method
       $records = $this->items;

       //Get the columns registered in the get_columns and get_sortable_columns methods
       list( $columns, $hidden ) = $this->get_column_info();

       //Loop for each record
       if(!empty($records)){foreach($records as $service){

          //Open the line
          echo '<tr id="record_'.$service['service_name'].'">';

          foreach ( $columns as $column_name => $column_display_name ) {

             //Style attributes for each col
             $class = "class='$column_name column-$column_name'";
             $style = "";
             if ( in_array( $column_name, $hidden ) ) $style = ' style="display:none;"';
             $attributes = $class . $style;

             //edit link
             $editlink  = '/wp-admin/link.php?action=edit&service_name='.(int)$service['service_name'];

             //Display the cell
             switch ( $column_name ) {
                case "service_name":  echo '<td '.$attributes.'>'.$service['service_name'].'</td>';   break;
                case "is_supported": echo '<td '.$attributes.'>'.$service['is_supported'].'</td>'; break;
                case "service_status": echo '<td '.$attributes.'>'.$service['service_status'].'</td>'; break;
                case "service_version": echo '<td '.$attributes.'>'.$service['service_version'].'</td>'; break;
             }
          }

          //Close the line
          echo'</tr>';
       }}
    }

}
