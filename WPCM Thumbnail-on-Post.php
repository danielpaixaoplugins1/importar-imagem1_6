<?php
/*
Plugin Name: WPCM Thumbnail on Post
Description: Varre postagens para identificar URLs de imagens externas, importa essas imagens para a biblioteca de mídia, anexa-as às postagens com um ID exclusivo, e define a primeira imagem com pelo menos 300 pixels de largura como imagem destacada. Além disso, atribui automaticamente uma imagem destacada a cada postagem processada.
Version: 1.6
Author: Daniel Oliveira da Paixão
*/

// Adiciona um item de menu para o painel de controle do plugin
function wpcm_add_admin_menu() {
    add_options_page('WPCM Thumbnail on Post', 'WPCM Thumbnail', 'manage_options', 'wpcm-thumbnail-on-post', 'wpcm_admin_page');
}

// Renderiza a página de administração do plugin
function wpcm_admin_page() {
    // Verifica se a página foi carregada com sucesso após processamento
    $success = isset($_GET['success']) ? $_GET['success'] : '';
    ?>
    <div class="wrap">
        <h1>WPCM Thumbnail on Post</h1>
        <?php if ($success === '1'): ?>
            <div id="message" class="updated notice notice-success is-dismissible">
                <p>Postagens processadas com sucesso.</p>
            </div>
        <?php endif; ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('wpcm_plugin_settings');
            do_settings_sections('wpcm_plugin_settings');
            submit_button('Salvar Configurações');
            ?>
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="wpcm_process_posts">
            <?php
            wp_nonce_field('wpcm_process_posts_action', 'wpcm_process_posts_nonce');
            submit_button('Processar Postagens');
            ?>
        </form>
    </div>
    <?php
}

// Registra as configurações do plugin
function wpcm_register_settings() {
    register_setting('wpcm_plugin_settings', 'wpcm_post_count', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 5,
    ]);
    add_settings_section('wpcm_plugin_settings_section', 'Configurações', null, 'wpcm_plugin_settings');
    add_settings_field('wpcm_post_count', 'Número de Postagens a Processar', 'wpcm_post_count_render', 'wpcm_plugin_settings', 'wpcm_plugin_settings_section');
}

// Renderiza o campo para inserir o número de postagens a processar
function wpcm_post_count_render() {
    $value = get_option('wpcm_post_count', 5);
    echo "<input type='number' name='wpcm_post_count' value='{$value}' min='1' />";
}

// Processa as postagens para importar URLs externas e definir a imagem destacada
function wpcm_process_posts() {
    if (
        isset($_POST['wpcm_process_posts_nonce']) &&
        wp_verify_nonce($_POST['wpcm_process_posts_nonce'], 'wpcm_process_posts_action') &&
        current_user_can('manage_options')
    ) {
        $post_count = get_option('wpcm_post_count', 5);
        $posts = get_posts([
            'numberposts' => $post_count,
            'post_status' => 'publish'
        ]);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        foreach ($posts as $post) {
            $content = $post->post_content;
            if (preg_match_all('/<img[^>]+src=[\'"]([^\'"]+)[\'"].*>/i', $content, $matches)) {
                $first_image_set = false;
                foreach ($matches[1] as $image_url) {
                    if (strpos($image_url, get_site_url()) === false) {
                        $image_id = media_sideload_image($image_url, $post->ID, null, 'id');
                        if (!is_wp_error($image_id)) {
                            $image_attributes = wp_get_attachment_image_src($image_id, 'full');
                            if ($image_attributes && $image_attributes[1] >= 300 && !$first_image_set) {
                                set_post_thumbnail($post->ID, $image_id);
                                $first_image_set = true;
                            }
                        }
                    }
                }
                // Se nenhuma imagem externa foi definida como destacada, tenta atribuir uma imagem do
