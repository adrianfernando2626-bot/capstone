$(document).ready(function () {

  // 🔍 Search by name
  $('#searchInput').on('input', function () {
    const keyword = $(this).val().toLowerCase();
    $('.tenant-row').each(function () {
      const name = $(this).find('td').eq(1).text().toLowerCase();
      $(this).toggle(name.includes(keyword));
    });
  });

  // 📑 Filters + Pagination
  function applyFiltersAndPagination() {
    const selectedStatus = $('#filterStatus').val();
    const selectedPayment = $('#filterPayment').val();
    const rowsPerPage = parseInt($('#rowsPerPage').val());

    let rows = $('.tenant-row');
    let filtered = [];

    rows.each(function () {
      const row = $(this);
      const status = row.data('status');
      const payment = row.data('payment');

      const matchStatus = (selectedStatus === 'all' || status === selectedStatus);
      const matchPayment = (selectedPayment === 'all' || payment.includes(selectedPayment));

      if (matchStatus && matchPayment) {
        filtered.push(row);
      } else {
        row.hide();
      }
    });

    filtered.forEach((row, index) => {
      if (index < rowsPerPage) {
        row.show();
      } else {
        row.hide();
      }
    });
  }

  $('#applyFiltersBtn').on('click', applyFiltersAndPagination);

});

