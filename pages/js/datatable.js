$(document).ready(function () {
    const $table = $('#rulesTable');
    if ($table.length) {
        const table = $table.DataTable({
            paging: true,
            lengthChange: false,
            pageLength: 15,
            searching: true,
            ordering: false,
            info: false,
            dom: 'lrtip' // Adjust this if needed
        });
        $('#rowsPerPage').on('change', function () {
            const newLength = parseInt($(this).val(), 10);
            if (!isNaN(newLength)) {
                table.page.len(newLength).draw();
            }
        });
    } else {
        console.error("Table with ID 'rulesTable' not found.");
    }


});