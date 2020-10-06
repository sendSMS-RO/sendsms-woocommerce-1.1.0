<?php
class WC_SendSMS_History_List_Table extends WP_List_Table {
    /**
     * Get list columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'id'            => __('ID', 'wc_sendsms'),
            'phone'         => __('Telefon', 'wc_sendsms'),
            'status'         => __('Status', 'wc_sendsms'),
            'message'         => __('Răspuns', 'wc_sendsms'),
            'details'         => __('Detalii', 'wc_sendsms'),
            'content'         => __('Conținut', 'wc_sendsms'),
            'type'         => __('Tip', 'wc_sendsms'),
            'sent_on'         => __('Data', 'wc_sendsms'),
        );
    }

    /**
     * Column cb.
     */
    public function column_cb($issue) {
        return sprintf( '<input type="checkbox" name="wc_sendsms_history[]" value="%1$s" />', $issue['id'] );
    }

    /**
     * Return ID column
     */
    public function column_id( $issue ) {
        return $issue['id'];
    }

    /**
     * Return phone column
     */
    public function column_phone( $issue ) {
        return $issue['phone'];
    }

    /**
     * Return message column
     */
    public function column_message( $issue ) {
        return $issue['message'];
    }

    /**
     * Return status column
     */
    public function column_status( $issue ) {
        return $issue['status'];
    }

    /**
     * Return details column
     */
    public function column_details( $issue ) {
        return $issue['details'];
    }

    /**
     * Return content column
     */
    public function column_content( $issue ) {
        return $issue['content'];
    }

    /**
     * Return type column
     */
    public function column_type( $issue ) {
        return $issue['type'];
    }

    /**
     * Return sent_on column
     */
    public function column_sent_on( $issue ) {
        return $issue['sent_on'];
    }

    /**
     * Get bulk actions.
     *
     * @return array
     */
    protected function get_bulk_actions() {
        return array(

        );
    }

    public function get_sortable_columns()
    {
        return array(
            'sent_on' => array('sent_on', true),
            'id' => array('id', true),
            'status' => array('status', true),
            'phone' => array('phone', true),
            'message' => array('message', true),
            'details' => array('details', true),
            'content' => array('content', true),
            'type' => array('type', true)
        );
    }

    /**
     * Prepare table list items.
     */
    public function prepare_items() {
        global $wpdb;

        $per_page = 10;
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $table_name = $wpdb->prefix . 'wcsendsms_history';

        // Column headers
        $this->_column_headers = array($columns, $hidden, $sortable);

        $current_page = $this->get_pagenum();
        if (1 < $current_page) {
            $offset = $per_page * ( $current_page - 1 );
        } else {
            $offset = 0;
        }

        $search = '';

        if (!empty($_REQUEST['s'])) {
            $search = "AND phone LIKE '%" . esc_sql($wpdb->esc_like($_REQUEST['s'])) . "%' ";
            $search .= "OR message LIKE '%" . esc_sql($wpdb->esc_like($_REQUEST['s'])) . "%' ";
            $search .= "OR content LIKE '%" . esc_sql($wpdb->esc_like($_REQUEST['s'])) . "%' ";
            $search .= "OR `type` LIKE '%" . esc_sql($wpdb->esc_like($_REQUEST['s'])) . "%' ";
            $search .= "OR details LIKE '%" . esc_sql($wpdb->esc_like($_REQUEST['s'])) . "%' ";
            $search .= "OR sent_on LIKE '%" . esc_sql($wpdb->esc_like($_REQUEST['s'])) . "%' ";
        }


        if (isset($_GET['orderby']) && isset($columns[$_GET['orderby']])) {
            $orderBy = $_GET['orderby'];
            if (isset($_GET['order']) && in_array(strtolower($_GET['order']), array('asc', 'desc'))) {
                $order = $_GET['order'];
            } else {
                $order = 'ASC';
            }
        } else {
            $orderBy = 'id';
            $order = 'DESC';
        }

        $items = $wpdb->get_results(
            "SELECT id, phone, status, message, details, content, `type`, sent_on FROM $table_name WHERE 1 = 1 {$search}" .
            $wpdb->prepare("ORDER BY `$orderBy` $order LIMIT %d OFFSET %d;", $per_page, $offset ), ARRAY_A
        );

        $count = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE 1 = 1 {$search};");

        $this->items = $items;

        // Set the pagination
        $this->set_pagination_args(array(
            'total_items' => $count,
            'per_page'    => $per_page,
            'total_pages' => ceil( $count / $per_page )
        ));
    }
}
