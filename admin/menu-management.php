<?php
// Render the Menu Management interface
function kwetupizza_render_menu_management() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_products';

    // Fetch menu items from the database
    $menu_items = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC");

    // Fetch currency from settings
    $currency = get_option('kwetupizza_currency', 'TZS'); // Default to TZS if not set

    echo "<h1>Menu Management</h1>";
    
    // Add New Menu Item Button
    echo "<button id='add-new-menu-item' class='button button-primary'>Add New Menu Item</button><br><br>";

    // Updated table header to include 'Category'
    echo "<table class='widefat fixed' cellspacing='0'>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Currency</th>
                    <th>Category</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>";

    if ($menu_items) {
        foreach ($menu_items as $item) {
            echo "<tr>
                    <td>{$item->id}</td>
                    <td>{$item->product_name}</td>
                    <td>{$item->description}</td>
                    <td>{$item->price}</td>
                    <td>{$item->currency}</td>
                    <td>{$item->category}</td>
                    <td>
                        <button class='edit-menu-item button' data-id='{$item->id}' data-name='{$item->product_name}' data-description='{$item->description}' data-price='{$item->price}' data-currency='{$item->currency}' data-category='{$item->category}'>Edit</button>
                        <button class='delete-menu-item button button-danger' data-id='{$item->id}'>Delete</button>
                    </td>
                </tr>";
        }
    } else {
        echo "<tr><td colspan='7'>No menu items found</td></tr>";
    }

    echo "</tbody></table>";

    // Add Modal HTML for Add/Edit
    echo '<div id="menu-item-modal" class="menu-item-modal" style="display:none;">
            <div class="menu-item-modal-content">
                <span class="close-modal">&times;</span>
                <h2 id="modal-title">Add New Menu Item</h2>
                <form id="menu-item-form">
                    <input type="hidden" id="menu-item-id" name="menu_item_id">
                    <label for="product_name">Product Name:</label><br>
                    <input type="text" id="product_name" name="product_name" required><br><br>

                    <label for="description">Description:</label><br>
                    <textarea id="description" name="description" ></textarea><br><br>

                    <label for="price">Price:</label><br>
                    <input type="number" step="0.01" id="price" name="price" required><br><br>

                    <label for="currency">Currency:</label><br>
                    <input type="text" id="currency" name="currency" value="TZS" ><br><br> <!-- Currency is fetched and displayed as readonly -->

                    <label for="category">Category:</label><br>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="Pizza">Pizza</option>
                        <option value="Drinks">Drinks</option>
                        <option value="Dessert">Dessert</option>
                    </select><br><br>

                    <button type="submit" class="button button-primary">Save Menu Item</button>
                </form>
            </div>
        </div>';

    echo '<style>
            /* Modal styling */
            .menu-item-modal {
                position: fixed;
                top: center;
                left: center;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: none;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            }
            .menu-item-modal-content {
                background: white;
                padding: 20px;
                border-radius: 5px;
                width: 500px;
                position: relative;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            }
            .close-modal {
                position: absolute;
                top: 10px;
                right: 15px;
                font-size: 20px;
                cursor: pointer;
            }
        </style>';

    echo '<script>
        jQuery(document).ready(function($) {
            // Open Add New Modal
            $("#add-new-menu-item").on("click", function() {
                $("#menu-item-id").val("");
                $("#product_name").val("");
                $("#description").val("");
                $("#price").val("");
                $("#currency").val(""); // Reset currency
                $("#category").val(""); // Reset category
                $("#modal-title").text("Add New Menu Item");
                $("#menu-item-modal").fadeIn();
            });

            // Open Edit Modal
            $(".edit-menu-item").on("click", function() {
                var id = $(this).data("id");
                var name = $(this).data("name");
                var description = $(this).data("description");
                var price = $(this).data("price");
                var currency = $(this).data("currency");
                var category = $(this).data("category"); // Get category

                $("#menu-item-id").val(id);
                $("#product_name").val(name);
                $("#description").val(description);
                $("#price").val(price);
                $("#currency").val(currency);
                $("#category").val(category); // Set category
                $("#modal-title").text("Edit Menu Item");
                $("#menu-item-modal").fadeIn();
            });

            // Close Modal
            $(".close-modal").on("click", function() {
                $("#menu-item-modal").fadeOut();
            });

            // Handle form submit (Ajax for Add/Edit)
            $("#menu-item-form").on("submit", function(e) {
                e.preventDefault();

                var formData = $(this).serialize();

                // Debug: Log formData to check if all fields are captured correctly
                console.log(formData);

                $.post(ajaxurl, {
                    action: "kwetupizza_save_menu_item",
                    data: formData
                })
                .done(function(response) {
                    // Check for successful response
                    if (response.success) {
                        alert(response.message); // Display success message
                        location.reload(); // Reload the page to see changes
                    } else {
                        // Handle error response (if success is false)
                        alert("Error: " + response.data.message); // Show error message from server
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    // Handle AJAX failure
                    console.error("AJAX Error: ", textStatus, errorThrown);
                    alert("An error occurred while saving the menu item: " + errorThrown);
                });
            });

            // Handle delete button
            $(".delete-menu-item").on("click", function() {
                if (confirm("Are you sure you want to delete this menu item?")) {
                    var id = $(this).data("id");
                    $.post(ajaxurl, {
                        action: "kwetupizza_delete_menu_item",
                        id: id
                    }, function(response) {
                        alert(response.message);
                        location.reload();
                    });
                }
            });
        });
    </script>';
}

// Handle the Ajax request for saving menu item
function kwetupizza_save_menu_item() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_products';

    // Parse the form data
    if (isset($_POST['data'])) {
        $data = [];
        parse_str($_POST['data'], $data); // Convert the serialized form data to an array

        $menu_item_id = intval($data['menu_item_id']);
        $product_name = sanitize_text_field($data['product_name']);
        $category = sanitize_text_field($data['category']);
        $description = sanitize_textarea_field($data['description']);
        $price = floatval($data['price']);
        $currency = sanitize_text_field($data['currency']);

        // Check if all necessary fields are present
        if (empty($product_name) || empty($description) || empty($price) || empty($currency) || empty($category)) {
            wp_send_json_error(['message' => 'All fields are required.']);
        }

        // Find the lowest available id (reuse deleted ids)
        if ($menu_item_id == 0) {
            $lowest_id = $wpdb->get_var("SELECT MIN(t1.id + 1) FROM $table_name t1 LEFT JOIN $table_name t2 ON t1.id + 1 = t2.id WHERE t2.id IS NULL");
            if (!$lowest_id) {
                $lowest_id = 1;
            }

            // Insert new menu item with the calculated lowest id
            $wpdb->query("ALTER TABLE $table_name AUTO_INCREMENT = 1"); // Reset AUTO_INCREMENT to 1 for consistency
            $wpdb->insert(
                $table_name,
                [
                    'id' => $lowest_id,
                    'product_name' => $product_name,
                    'description' => $description,
                    'price' => $price,
                    'currency' => $currency,
                    'category' => $category
                ]
            );
            wp_send_json_success(['message' => 'Menu item added successfully']);
        } else {
            // Update existing menu item
            $wpdb->update(
                $table_name,
                [
                    'product_name' => $product_name,
                    'description' => $description,
                    'price' => $price,
                    'currency' => $currency,
                    'category' => $category
                ],
                ['id' => $menu_item_id]
            );
            wp_send_json_success(['message' => 'Menu item updated successfully']);
        }
    } else {
        wp_send_json_error(['message' => 'Invalid request.']);
    }

    wp_die();
}
add_action('wp_ajax_kwetupizza_save_menu_item', 'kwetupizza_save_menu_item');

// Handle the Ajax request for deleting menu item and resequence IDs
function kwetupizza_delete_menu_item() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_products';
    $id = intval($_POST['id']);

    // Delete the item
    $wpdb->delete($table_name, ['id' => $id]);

    // Resequence IDs
    $menu_items = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC");
    $new_id = 1;
    foreach ($menu_items as $item) {
        $wpdb->update($table_name, ['id' => $new_id], ['id' => $item->id]);
        $new_id++;
    }

    wp_send_json_success(['message' => 'Menu item deleted and IDs resequenced successfully']);
    wp_die();
}
add_action('wp_ajax_kwetupizza_delete_menu_item', 'kwetupizza_delete_menu_item');
