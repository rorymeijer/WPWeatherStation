jQuery(document).ready(function($) {
    $('#select-all').change(function() {
        var checkboxes = $(this).closest('table').find('tbody input[type="checkbox"]');
        checkboxes.prop('checked', $(this).is(':checked'));
    });
});