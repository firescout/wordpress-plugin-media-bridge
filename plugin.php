<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Media bridge
 * Description:       Downloads media form URL and saves it into WP media.
 * Version:           1.0
 * Author:            Orel Krispel
 */

class Media_Bridge_API extends WP_REST_Controller
{
    public function init()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        $version = '1';
        $namespace = 'media-bridge/v' . $version;
        $base = 'media';
        register_rest_route($namespace, "/" . $base . "/upload", array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, "media_bridge_upload"),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => array()
            )
        ));
    }

    /**
     * Start upload from URL
     */
    public function media_bridge_upload()
    {
        //clean post data
        $payload = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

        if (!isset($payload['url'])) {
            return new WP_Error('upload', __('Upload Failed, Missing path', 'text-domain'), array('status' => 400));
        }

        $upload = $this->mb_upload_from_url($payload['url'], $payload['filename']);
        if ($upload == true) {
            return new WP_REST_Response("File uploaded", 200);
        }

        return new WP_Error('upload', __($upload, 'text-domain'), array('status' => 500));
    }

    /**
     * Check if a given request has access to create items
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function permissions_check($request)
    {
        return current_user_can('edit_others_posts');
        // return current_user_can( 'edit_something' );
    }

    /**
     * Prepare the item for create or update operation
     *
     * @param WP_REST_Request $request Request object
     * @return WP_Error|object $prepared_item
     */
    protected function prepare_item_for_database($request)
    {
        return array();
    }

    public function mb_upload_from_url($url, $title = null)
    {
        require_once(ABSPATH . "/wp-load.php");
        require_once(ABSPATH . "/wp-admin/includes/image.php");
        require_once(ABSPATH . "/wp-admin/includes/file.php");
        require_once(ABSPATH . "/wp-admin/includes/media.php");
        // Download url to a temp file
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return "Unable to download url to a temp file";
        }

        // Get the filename and extension ("photo.png" => "photo", "png")
        $filename = pathinfo($url, PATHINFO_FILENAME);
        $extension = pathinfo($url, PATHINFO_EXTENSION);

        // An extension is required or else WordPress will reject the upload
        if (!$extension) {
            // Look up mime type, example: "/photo.png" -> "image/png"
            $mime = mime_content_type($tmp);
            $mime = is_string($mime) ? sanitize_mime_type($mime) : false;

            // Only allow certain mime types because mime types do not always end in a valid extension (see the .doc example below)
            $mime_extensions = array(
                // mime_type         => extension (no period)
                'text/plain'         => 'txt',
                'text/csv'           => 'csv',
                'application/msword' => 'doc',
                'image/jpg'          => 'jpg',
                'image/jpeg'         => 'jpeg',
                'image/gif'          => 'gif',
                'image/png'          => 'png',
                'video/mp4'          => 'mp4',
            );

            if (isset($mime_extensions[$mime])) {
                // Use the mapped extension
                $extension = $mime_extensions[$mime];
            } else {
                // Could not identify extension
                @unlink($tmp);
                return "Could not identify extension";
            }
        }

        // Upload by "sideloading": "the same way as an uploaded file is handled by media_handle_upload"
        $args = array(
            'name' => "$filename.$extension",
            'tmp_name' => $tmp,
        );

        // Do the upload
        $attachment_id = media_handle_sideload($args, 0, $title);

        // Cleanup temp file
        @unlink($tmp);

        // Error uploading
        if (is_wp_error($attachment_id)) {
            return "Error uploading";
        }

        // Success, return attachment ID (int)
        return array("status" => "success", "message" => "File uploaded");
    }
}

$mbApi = new Media_Bridge_API();
$mbApi->init();
