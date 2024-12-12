<?php
/*
Plugin Name: HeyTabs
Description: Create, manage, and display horizontal tabs with modals using unique shortcodes.
Version: 3.3
Author: Aaditya Uzumaki
Author URI: https://goenka.xyz
License: AAL-1.0
*/

if (!defined('ABSPATH')) {
    exit;
}

// Activate the plugin: Create the database table
function horizontal_tabs_plugin_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'horizontal_tabs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        tab_group_name VARCHAR(255) NOT NULL,
        tab_group_data LONGTEXT NOT NULL,
        shortcode VARCHAR(50) NOT NULL UNIQUE,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'horizontal_tabs_plugin_activate');

// Add admin menu
function horizontal_tabs_plugin_admin_menu() {
    add_menu_page(
        'Horizontal Tabs Manager',
        'Horizontal Tabs',
        'manage_options',
        'horizontal-tabs-manager',
        'horizontal_tabs_plugin_admin_page',
        'dashicons-admin-page',
        20
    );
}
add_action('admin_menu', 'horizontal_tabs_plugin_admin_menu');

// Admin page
function horizontal_tabs_plugin_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'horizontal_tabs';

    // Handle form submissions for Create/Update/Delete
    if (isset($_POST['save_tab_group'])) {
        $tab_group_name = sanitize_text_field($_POST['tab_group_name']);
        $tabs = [];

        if (isset($_POST['tab_titles'])) {
            foreach ($_POST['tab_titles'] as $index => $title) {
                $tabs[] = [
                    'title' => sanitize_text_field($title),
                    'image' => esc_url_raw($_POST['tab_images'][$index]),
                    'client' => sanitize_text_field($_POST['tab_clients'][$index]),
                    'length' => sanitize_text_field($_POST['tab_lengths'][$index]),
                    'lane' => sanitize_text_field($_POST['tab_lanes'][$index]),
                    'status' => sanitize_text_field($_POST['tab_statuses'][$index]),
                ];
            }
        }

        $tab_group_data = json_encode($tabs);
        $shortcode = 'heytabs' . rand(100, 999);

        if (!empty($_POST['tab_group_id'])) {
            $tab_group_id = intval($_POST['tab_group_id']);
            $wpdb->update(
                $table_name,
                ['tab_group_name' => $tab_group_name, 'tab_group_data' => $tab_group_data],
                ['id' => $tab_group_id]
            );
        } else {
            $wpdb->insert(
                $table_name,
                ['tab_group_name' => $tab_group_name, 'tab_group_data' => $tab_group_data, 'shortcode' => $shortcode]
            );
        }
    } elseif (isset($_GET['delete_tab_group'])) {
        $tab_group_id = intval($_GET['delete_tab_group']);
        $wpdb->delete($table_name, ['id' => $tab_group_id]);
    }

    // Fetch all tab groups
    $tab_groups = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    // Fetch the group data for editing
    $edit_group = null;
    if (isset($_GET['edit_tab_group'])) {
        $edit_group_id = intval($_GET['edit_tab_group']);
        $edit_group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_group_id), ARRAY_A);
        $edit_group_data = $edit_group ? json_decode($edit_group['tab_group_data'], true) : [];
    }

    // Admin UI
    ?>
    <div class="wrap">
        <h1>Horizontal Tabs Manager</h1>

        <h2><?php echo isset($edit_group) ? 'Edit Tab Group' : 'Create Tab Group'; ?></h2>
        <form method="post">
            <input type="hidden" name="tab_group_id" value="<?php echo isset($edit_group) ? intval($edit_group['id']) : ''; ?>">
            <table class="form-table">
                <tr>
                    <th><label for="tab_group_name">Tab Group Name</label></th>
                    <td><input type="text" name="tab_group_name" id="tab_group_name" class="regular-text" value="<?php echo isset($edit_group) ? esc_attr($edit_group['tab_group_name']) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label>Tabs</label></th>
                    <td>
                        <div id="tab-group-container">
                            <?php if (isset($edit_group_data)) : ?>
                                <?php foreach ($edit_group_data as $tab) : ?>
                                    <div class="tab-item">
                                        <input type="text" name="tab_titles[]" placeholder="Title" value="<?php echo esc_attr($tab['title']); ?>" required>
                                        <input type="url" name="tab_images[]" placeholder="Image URL" value="<?php echo esc_url($tab['image']); ?>" required>
                                        <input type="text" name="tab_clients[]" placeholder="Client" value="<?php echo esc_attr($tab['client']); ?>" required>
                                        <input type="text" name="tab_lengths[]" placeholder="Length" value="<?php echo esc_attr($tab['length']); ?>" required>
                                        <input type="text" name="tab_lanes[]" placeholder="Lane Type" value="<?php echo esc_attr($tab['lane']); ?>" required>
                                        <input type="text" name="tab_statuses[]" placeholder="Status" value="<?php echo esc_attr($tab['status']); ?>" required>
                                        <button type="button" class="button remove-tab">Remove</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" id="add-tab" class="button">Add Tab</button>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="save_tab_group" id="save_tab_group" class="button button-primary" value="Save Tab Group">
            </p>
        </form>

        <h2>Existing Tab Groups</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Shortcode</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($tab_groups)) : ?>
                    <?php foreach ($tab_groups as $group) : ?>
                        <tr>
                            <td><?php echo esc_html($group['id']); ?></td>
                            <td><?php echo esc_html($group['tab_group_name']); ?></td>
                            <td>[<?php echo esc_html($group['shortcode']); ?>]</td>
                            <td>
                                <a href="?page=horizontal-tabs-manager&edit_tab_group=<?php echo $group['id']; ?>" class="button">Edit</a>
                                <a href="?page=horizontal-tabs-manager&delete_tab_group=<?php echo $group['id']; ?>" class="button button-danger" onclick="return confirm('Are you sure?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4">No tab groups found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('tab-group-container');
    const addButton = document.getElementById('add-tab');

    addButton.addEventListener('click', () => {
        const div = document.createElement('div');
        div.className = 'tab-item';
        div.innerHTML = `
            <input type="text" name="tab_titles[]" placeholder="Title" required>
            <input type="url" name="tab_images[]" placeholder="Image URL" required>
            <input type="text" name="tab_clients[]" placeholder="Client" required>
            <input type="text" name="tab_lengths[]" placeholder="Length" required>
            <input type="text" name="tab_lanes[]" placeholder="Lane Type" required>
            <input type="text" name="tab_statuses[]" placeholder="Status" required>
            <button type="button" class="button remove-tab">Remove</button>
        `;
        container.appendChild(div);

        div.querySelector('.remove-tab').addEventListener('click', () => {
            div.remove();
        });
    });

    container.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-tab')) {
            e.target.parentElement.remove();
        }
    });
});

    </script>
    <?php
}
function heytabs_enqueue_assets() {
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js', [], null, true);
}
add_action('wp_enqueue_scripts', 'heytabs_enqueue_assets');

function horizontal_tabs_plugin_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'horizontal_tabs';

    $shortcode = isset($atts['shortcode']) ? sanitize_text_field($atts['shortcode']) : '';
    $tab_group = $wpdb->get_row($wpdb->prepare("SELECT tab_group_data FROM $table_name WHERE shortcode = %s", $shortcode), ARRAY_A);

    if (!$tab_group) {
        return '<p>Tab group not found.</p>';
    }

    $tabs = json_decode($tab_group['tab_group_data'], true);

    ob_start();
    ?>
    <div class="container death-tabs-container">
        <ul class="nav nav-tabs death-nav-tabs" id="heytabs-tabs-death" role="tablist">
            <li class="nav-item death-nav-item">
                <button class="nav-link death-nav-link active" id="all-tab-death" data-bs-toggle="tab" data-bs-target="#all-death" type="button" role="tab" aria-controls="all-death" aria-selected="true">
                    All
                </button>
            </li>
            <?php foreach ($tabs as $index => $tab) : ?>
                <li class="nav-item death-nav-item">
                    <button class="nav-link death-nav-link" id="tab-<?php echo $index; ?>-tab-death" data-bs-toggle="tab" data-bs-target="#tab-<?php echo $index; ?>-death" type="button" role="tab" aria-controls="tab-<?php echo $index; ?>-death" aria-selected="false">
                        <?php echo esc_html($tab['title']); ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="tab-content death-tab-content mt-3">
            <!-- All Tab Content -->
            <div class="tab-pane fade show active death-tab-pane" id="all-death" role="tabpanel" aria-labelledby="all-tab-death">
                <div class="row">
                    <?php foreach ($tabs as $index => $tab) : ?>
                        <div class="col-md-4 death-tab-card-col">
                            <div class="card death-tab-card mb-4">
                                <img src="<?php echo esc_url($tab['image']); ?>" class="card-img-top death-card-img" alt="<?php echo esc_attr($tab['title']); ?>" data-bs-toggle="modal" data-bs-target="#modal-<?php echo $index; ?>-death">
                                <div class="card-body death-card-body">
                                    <h5 class="card-title death-card-title"><?php echo esc_html($tab['title']); ?></h5>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Individual Tabs -->
            <?php foreach ($tabs as $index => $tab) : ?>
                <div class="tab-pane fade death-tab-pane" id="tab-<?php echo $index; ?>-death" role="tabpanel" aria-labelledby="tab-<?php echo $index; ?>-tab-death">
                    <div class="card death-tab-card">
                        <img src="<?php echo esc_url($tab['image']); ?>" class="card-img-top death-card-img" alt="<?php echo esc_attr($tab['title']); ?>" data-bs-toggle="modal" data-bs-target="#modal-<?php echo $index; ?>-death">
                        <div class="card-body death-card-body">
                            <h5 class="card-title death-card-title"><?php echo esc_html($tab['title']); ?></h5>
                        </div>
                    </div>
                </div>

                <!-- Modal -->
                <div class="modal fade death-modal" id="modal-<?php echo $index; ?>-death" tabindex="-1" aria-labelledby="modalLabel-<?php echo $index; ?>-death" aria-hidden="true">
                    <div class="modal-dialog death-modal-dialog">
                        <div class="modal-content death-modal-content">
                            <div class="modal-header death-modal-header">
                                <h5 class="modal-title death-modal-title" id="modalLabel-<?php echo $index; ?>-death"><?php echo esc_html($tab['title']); ?></h5>
                                <button type="button" class="btn-close death-btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body death-modal-body">
                                <img src="<?php echo esc_url($tab['image']); ?>" class="img-fluid death-modal-img" alt="<?php echo esc_attr($tab['title']); ?>">
                                <p><strong>Client:</strong> <?php echo esc_html($tab['client']); ?></p>
                                <p><strong>Length:</strong> <?php echo esc_html($tab['length']); ?></p>
                                <p><strong>Lane Type:</strong> <?php echo esc_html($tab['lane']); ?></p>
                                <p><strong>Status:</strong> <?php echo esc_html($tab['status']); ?></p>
                            </div>
                            <div class="modal-footer death-modal-footer">
                                <button type="button" class="btn btn-secondary death-btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <style>
        .death-nav-tabs .death-nav-link {
            border: 1px solid #dee2e6;
            border-radius: 0.25rem 0.25rem 0 0;
            background-color: #f8f9fa;
            color: #495057;
        }

        .death-nav-tabs .death-nav-link.active {
            background-color: #6c757d;
            color: #fff;
            border-color: #6c757d #6c757d #fff;
        }

        .death-tab-content .death-tab-card {
            border: 1px solid #ddd;
            border-radius: 0.25rem;
        }

        .death-tab-content .death-card-img {
            max-height: 300px;
            object-fit: cover;
            cursor: pointer;
        }

        .death-modal-body img {
            max-width: 100%;
            margin-bottom: 20px;
        }

        .death-tab-content .death-tab-card-col {
            padding-bottom: 15px;
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('heytabs', 'horizontal_tabs_plugin_shortcode');
