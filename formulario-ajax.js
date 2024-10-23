jQuery(document).ready(function($) {
    $('#formularioEstimaciones').on('submit', function(e) {
        e.preventDefault(); // Prevenir envío normal del formulario

        var formData = {
            action: 'procesar_formulario_estimaciones',
            especialidad: $('#especialidad').val(),
            puntaje: $('#puntaje').val(),
            correo: $('#correo').val()
        };

        $.post(formularioajax.ajaxurl, formData, function(response) {
            var data = JSON.parse(response);
            $('#respuesta').html('<p>' + data.mensaje + '</p>');
        });
    });
});
