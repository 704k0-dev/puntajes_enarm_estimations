<?php
/*
Plugin Name: Formulario de Estimaciones de Puntajes
Plugin URI: https://puntajesenarm.com
Description: Un plugin para gestionar las estimaciones de puntajes y guardar respuestas de los usuarios.
Version: 1.0
Author: 704k0
Author URI: https://704k0.com
*/

// Evitar el acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

// Crear tabla personalizada al activar el plugin
function crear_tabla_respuestas() {
    global $wpdb;
    $tabla_respuestas = $wpdb->prefix . 'respuestas_puntajes';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $tabla_respuestas (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        especialidad_id bigint(20) NOT NULL,
        puntaje DECIMAL(10,4) NOT NULL,
        correo varchar(100) NOT NULL,
        fecha datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'crear_tabla_respuestas');

// Encolar scripts para AJAX
function encolar_scripts_formulario() {
    wp_enqueue_script('formulario-ajax', plugin_dir_url(__FILE__) . 'js/formulario-ajax.js', array('jquery'), time(), true);
    wp_localize_script('formulario-ajax', 'formularioajax', array('ajaxurl' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'encolar_scripts_formulario');

// Shortcode para mostrar el formulario
function mostrar_formulario_estimaciones() {
    ob_start();
    ?>
    <form id="formularioEstimaciones" method="POST" action="">
        <label for="especialidad">Elige tu especialidad:</label>
        <select id="especialidad" name="especialidad" required>
            <?php
            $especialidades_query = new WP_Query(array(
                'post_type' => 'especialidad',
                'posts_per_page' => -1,
                'order' => 'ASC'
            ));

            if ($especialidades_query->have_posts()) {
                while ($especialidades_query->have_posts()) {
                    $especialidades_query->the_post();
                    $especialidad_id = get_the_ID();
                    $especialidad_titulo = get_the_title();
                    echo "<option value='$especialidad_id'>$especialidad_titulo</option>";
                }
                wp_reset_postdata();
            } else {
                echo '<option>No se encontraron especialidades</option>';
            }
            ?>
        </select><br><br>

        <label for="puntaje">Introduce tu puntaje:</label>
        <input type="text" id="puntaje" name="puntaje" pattern="^\d+(\.\d{1,4})?$" required><br><br>

        <label for="correo">Tu correo electrónico:</label>
        <input type="email" id="correo" name="correo" required><br><br>

        <input type="submit" name="submit_form" value="Enviar">
    </form>

    <div id="respuesta"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('formulario_estimaciones', 'mostrar_formulario_estimaciones');

// Manejar la lógica del formulario a través de AJAX
function procesar_formulario_estimaciones() {
    global $wpdb;
    $tabla_respuestas = $wpdb->prefix . 'respuestas_puntajes';
    
    $especialidad_id = intval($_POST['especialidad']);
    $puntaje_usuario = floatval($_POST['puntaje']);
    $correo_usuario = sanitize_email($_POST['correo']);
    
    // Verificar si ya existe una entrada con el mismo correo para evitar duplicados
    $existe_respuesta = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $tabla_respuestas WHERE correo = %s AND especialidad_id = %d",
        $correo_usuario, $especialidad_id
    ));

    if (!$existe_respuesta) {
        // Insertar nueva respuesta en la tabla
        $wpdb->insert(
            $tabla_respuestas,
            array(
                'especialidad_id' => $especialidad_id,
                'puntaje' => $puntaje_usuario,
                'correo' => $correo_usuario,
            )
        );

        // Obtener el puntaje mínimo estimado para 2024 de la especialidad seleccionada
        $puntaje_estimado = get_post_meta($especialidad_id, 'puntaje_2024_min_mex', true);

        if ($puntaje_estimado !== '') {
            // Calcular el porcentaje de posibilidades
            $diferencia_porcentaje = (($puntaje_usuario - $puntaje_estimado) / $puntaje_estimado) * 100;

            if ($diferencia_porcentaje >= 10) {
                // Si la puntuación es 10% mayor o más, posibilidades 100%
                $posibilidades = 100;
            } elseif ($diferencia_porcentaje <= -10) {
                // Si la puntuación es 10% menor o más, posibilidades 0%
                $posibilidades = 0;
            } else {
                // Calcular proporcionalmente si está dentro del rango ±10%
                // Normalizamos la diferencia dentro de ese rango (10% a -10% corresponde a 100% a 0%)
                $posibilidades = round((($diferencia_porcentaje + 10) / 20) * 100, 2);
            }

            // Mensaje según el porcentaje
            if ($posibilidades >= 100) {
                $mensaje = "¡Gracias por participar! Tus posibilidades de obtener una plaza en " . get_the_title($especialidad_id) . " son del <strong>100%</strong>. ¡Felicitaciones, estamos seguros de que lo lograrás! 🎉";
            } elseif ($posibilidades > 0) {
                $mensaje = "¡Gracias por tu participación! Según nuestras estimaciones tienes un <strong>$posibilidades%</strong> de posibilidades de conseguir una plaza en " . get_the_title($especialidad_id) . ". ¡Esperamos que lo consigas! 😉";
            } else {
                $mensaje = "¡Gracias por participar! Aunque según nuestras estimaciones tus posibilidades actuales son bajas, ¡quién sabe! A lo mejor estamos equivocados y capaz que sí lo consigues. ¡Aún hay esperanza! 💪😊";
            }
        } else {
            $mensaje = "No se pudo encontrar el puntaje estimado para la especialidad seleccionada.";
        }
    } else {
        $mensaje = "Ya hemos recibido tu respuesta para esta especialidad.";
    }

    echo json_encode(array('mensaje' => $mensaje));
    wp_die();
}

add_action('wp_ajax_procesar_formulario_estimaciones', 'procesar_formulario_estimaciones');
add_action('wp_ajax_nopriv_procesar_formulario_estimaciones', 'procesar_formulario_estimaciones');

// Encolar el CSS para el formulario y las respuestas
function encolar_estilos_formulario() {
    wp_enqueue_style('estilos-formulario', plugin_dir_url(__FILE__) . 'css/formulario-estimaciones.css');
}
add_action('wp_enqueue_scripts', 'encolar_estilos_formulario');

?>