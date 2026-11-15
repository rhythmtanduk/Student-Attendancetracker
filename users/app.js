// app.js - small JavaScript shared by all pages (plain JavaScript, no library)

// 1. Open / close the sidebar on small screens
var sidebar = document.getElementById('sidebar');
var sidebarToggle = document.getElementById('sidebarToggle');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function () {
        sidebar.classList.toggle('active');
    });
}

// 2. Live search, class filter and PAGING for a table
//    The page needs: <table id="dataTable"> and every row must have the attribute data-row
//    Optional: <input id="searchBox">, <select id="classFilter">, <span id="rowCount">
var searchBox   = document.getElementById('searchBox');
var classFilter = document.getElementById('classFilter');
var dataTable   = document.getElementById('dataTable');
var rowCount    = document.getElementById('rowCount');

var pageSize    = 10;     // how many rows are shown on one page
var currentPage = 1;
var allRows     = [];     // every row of the table
var matchedRows = [];     // rows that match the search text and the class filter
var pageBox     = null;   // the box with the page buttons (made by JavaScript)
var printing    = false;  // true while printing (then all matching rows are shown)

if (dataTable) {
    allRows = Array.prototype.slice.call(dataTable.querySelectorAll('tbody tr[data-row]'));
}

// Make the box for the page buttons (only when the table has rows)
if (dataTable && allRows.length > 0) {
    pageBox = document.createElement('div');
    pageBox.className = 'd-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-top no-print';
    var card = dataTable.closest('.panel-card') || dataTable.parentElement;
    card.appendChild(pageBox);
}

// One button of the page list
function pageItem(label, page, disabled, active) {
    var cls = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
    return '<li class="' + cls + '"><a class="page-link" href="#" data-page="' + page + '">' + label + '</a></li>';
}

// Show the rows of the current page and draw the page buttons
function showPage() {
    if (!pageBox) { return; }

    var total = matchedRows.length;
    var totalPages = Math.max(1, Math.ceil(total / pageSize));
    if (currentPage > totalPages) { currentPage = totalPages; }

    var start = (currentPage - 1) * pageSize;
    var pageRows = printing ? matchedRows : matchedRows.slice(start, start + pageSize);

    // Hide every row, then show only the rows of this page
    allRows.forEach(function (row) { row.style.display = 'none'; });
    pageRows.forEach(function (row, i) {
        row.style.display = '';

        // Number the visible rows 1, 2, 3... (page 2 continues with 11, 12, 13...)
        var num = row.querySelector('.row-num');
        if (num) { num.textContent = (printing ? 0 : start) + i + 1; }
    });
    if (rowCount) { rowCount.textContent = pageRows.length; }

    // Text on the left, for example: Showing 11-20 of 49
    var info;
    if (total === 0) {
        info = 'No matching records';
    } else if (printing) {
        info = 'Showing 1-' + total + ' of ' + total;
    } else {
        info = 'Showing ' + (start + 1) + '-' + (start + pageRows.length) + ' of ' + total;
    }
    var html = '<div class="small text-muted">' + info + '</div>';

    // The page buttons are only needed when there are more than 10 rows
    if (total > 10) {
        html += '<div class="d-flex align-items-center gap-3 flex-wrap">';

        html += '<select class="form-select form-select-sm" id="pageSizeSelect" style="width: auto;">';
        [10, 25, 50, 100].forEach(function (n) {
            html += '<option value="' + n + '"' + (n === pageSize ? ' selected' : '') + '>' + n + ' per page</option>';
        });
        html += '</select>';

        html += '<ul class="pagination pagination-sm mb-0">';
        html += pageItem('&laquo; Prev', currentPage - 1, currentPage === 1, false);

        // Show at most 5 page numbers around the current page
        var first = Math.max(1, currentPage - 2);
        var last  = Math.min(totalPages, first + 4);
        first = Math.max(1, last - 4);

        if (first > 1) {
            html += pageItem('1', 1, false, false);
            if (first > 2) { html += '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
        }
        for (var p = first; p <= last; p++) {
            html += pageItem(p, p, false, p === currentPage);
        }
        if (last < totalPages) {
            if (last < totalPages - 1) { html += '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
            html += pageItem(totalPages, totalPages, false, false);
        }

        html += pageItem('Next &raquo;', currentPage + 1, currentPage === totalPages, false);
        html += '</ul></div>';
    }

    pageBox.innerHTML = html;
}

// Work out which rows match the search text and the class filter, then show page 1
function filterRows() {
    var text = searchBox ? searchBox.value.toLowerCase() : '';
    var cls  = classFilter ? classFilter.value.toLowerCase() : '';

    matchedRows = allRows.filter(function (row) {
        var matchText  = row.textContent.toLowerCase().indexOf(text) !== -1;
        var matchClass = cls === '' || (row.dataset.class || '').toLowerCase() === cls;
        return matchText && matchClass;
    });

    currentPage = 1;
    showPage();
}

if (searchBox) {
    searchBox.addEventListener('input', filterRows);
    // Pressing Enter in the search box must not reload the page
    searchBox.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); }
    });
}
if (classFilter) { classFilter.addEventListener('change', filterRows); }

if (pageBox) {
    // Click on a page button
    pageBox.addEventListener('click', function (e) {
        var link = e.target.closest('a[data-page]');
        if (!link) { return; }
        e.preventDefault();
        var item = link.parentElement;
        if (item.classList.contains('disabled') || item.classList.contains('active')) { return; }
        currentPage = parseInt(link.dataset.page, 10);
        showPage();
    });

    // "10 per page" list
    pageBox.addEventListener('change', function (e) {
        if (e.target.id === 'pageSizeSelect') {
            pageSize = parseInt(e.target.value, 10);
            currentPage = 1;
            showPage();
        }
    });

    // When printing, show all matching rows (not only the current page)
    window.addEventListener('beforeprint', function () { printing = true;  showPage(); });
    window.addEventListener('afterprint',  function () { printing = false; showPage(); });

    // Start: show page 1 now, and again after the browser has restored old form values
    filterRows();
    window.addEventListener('pageshow', function () {
        if (searchBox) { searchBox.value = ''; }
        if (classFilter) { classFilter.value = ''; }
        filterRows();
    });
}

// 3. Bootstrap form validation: add class="needs-validation" novalidate to a form
document.querySelectorAll('.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});

// 4. Success messages disappear by themselves after 4 seconds
document.querySelectorAll('.alert-success').forEach(function (box) {
    setTimeout(function () {
        if (window.bootstrap) { bootstrap.Alert.getOrCreateInstance(box).close(); }
    }, 4000);
});

// 5. Print button: <button id="printBtn">
var printBtn = document.getElementById('printBtn');
if (printBtn) {
    printBtn.addEventListener('click', function () { window.print(); });
}

// 6. Download ALL matching rows (not only the current page) as a CSV file: <button id="exportCsv">
function rowToCsv(tr) {
    var cells = [];
    tr.querySelectorAll('th, td').forEach(function (cell) {
        var text = cell.textContent.trim().replace(/\s+/g, ' ').replace(/"/g, '""');
        cells.push('"' + text + '"');
    });
    return cells.join(',');
}

var exportBtn = document.getElementById('exportCsv');
if (exportBtn && dataTable) {
    exportBtn.addEventListener('click', function () {
        var lines = [];
        var head = dataTable.querySelector('thead tr');
        if (head) { lines.push(rowToCsv(head)); }
        matchedRows.forEach(function (tr) { lines.push(rowToCsv(tr)); });

        // \ufeff makes Excel read the file as UTF-8
        var blob = new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = exportBtn.dataset.filename || 'table.csv';
        link.click();
    });
}

// 7. Notification bell: every 30 seconds, ask the server how many unread notifications there are
var bellBadge = document.getElementById('bellBadge');
if (bellBadge) {
    setInterval(function () {
        fetch('../notification/count.php')
            .then(function (response) { return response.json(); })
            .then(function (data) {
                bellBadge.textContent = data.unread;
                bellBadge.style.display = data.unread > 0 ? '' : 'none';
            })
            .catch(function () { /* if the request fails, we simply try again later */ });
    }, 30000);
}