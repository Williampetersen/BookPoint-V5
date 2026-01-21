<?php defined('ABSPATH') || exit;

$id = (int)($item['id'] ?? 0);
$label = (string)($item['label'] ?? '');
$name_key = (string)($item['name_key'] ?? '');
$type = (string)($item['type'] ?? 'text');
$required = isset($item['required']) ? (int)$item['required'] : 0;
$is_active = isset($item['is_active']) ? (int)$item['is_active'] : 1;
$sort_order = (int)($item['sort_order'] ?? 0);

$options_raw = '';
if (!empty($item['options_json'])) {
  $decoded = json_decode((string)$item['options_json'], true);
  if (is_array($decoded)) $options_raw = implode("\n", $decoded);
}

$types = ['text','email','tel','textarea','select','checkbox','radio','date'];
?>

<div class="wrap">
  <h1><?php echo $id ? esc_html__('Edit Field', 'bookpoint') : esc_html__('Add Field', 'bookpoint'); ?></h1>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('bp_admin'); ?>
    <input type="hidden" name="action" value="bp_admin_form_fields_save">
    <input type="hidden" name="id" value="<?php echo esc_attr((string)$id); ?>">
    <input type="hidden" name="scope" value="<?php echo esc_attr($scope); ?>">

    <table class="form-table">
      <tr>
        <th><label><?php echo esc_html__('Label', 'bookpoint'); ?></label></th>
        <td><input type="text" name="label" value="<?php echo esc_attr($label); ?>" class="regular-text" required></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Key (unique)', 'bookpoint'); ?></label></th>
        <td>
          <input type="text" name="name_key" value="<?php echo esc_attr($name_key); ?>" class="regular-text" required placeholder="e.g. company_name">
          <p class="description">Only lowercase/underscores recommended. Used as JSON key.</p>
        </td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Type', 'bookpoint'); ?></label></th>
        <td>
          <select name="type">
            <?php foreach ($types as $t): ?>
              <option value="<?php echo esc_attr($t); ?>" <?php selected($type, $t); ?>><?php echo esc_html($t); ?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Options', 'bookpoint'); ?></label></th>
        <td>
          <textarea name="options_raw" class="large-text" rows="5" placeholder="For select/radio: one option per line"><?php echo esc_textarea($options_raw); ?></textarea>
          <p class="description">Used only for select/radio. One per line (stored as JSON).</p>
        </td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Sort Order', 'bookpoint'); ?></label></th>
        <td><input type="number" name="sort_order" value="<?php echo esc_attr((string)$sort_order); ?>" class="small-text"></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Required', 'bookpoint'); ?></label></th>
        <td><label><input type="checkbox" name="required" value="1" <?php checked($required, 1); ?>> Required</label></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Active', 'bookpoint'); ?></label></th>
        <td><label><input type="checkbox" name="is_active" value="1" <?php checked($is_active, 1); ?>> Enabled</label></td>
      </tr>
    </table>

    <?php submit_button($id ? __('Save Changes', 'bookpoint') : __('Create Field', 'bookpoint')); ?>
  </form>
</div>
