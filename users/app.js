// app.js - small JavaScript shared by all pages (plain JavaScript, no library)

// 1. Open / close the sidebar on small screens
var sidebar = document.getElementById('sidebar');
var sidebarToggle = document.getElementById('sidebarToggle');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function () {
        sidebar.classList.toggle('active');
    });
}

// 2. Live search (and optional class filter) for a table
//    The page needs: <input id="searchBox">, <select id="classFilter"> (optional),
//    <table id="dataTable">, and every row must have the attribute data-row
var searchBox = document.getElementById('searchBox');
var classFilter = document.getElementById('classFilter');
var dataTable = document.getElementById('dataTable');
var rowCount = document.getElementById('rowCount');

function filterRows() {
    if (!dataTable) { return; }
    var text = searchBox ? searchBox.value.toLowerCase() : '';
    var cls = classFilter ? classFilter.value.toLowerCase() : '';
    var rows = dataTable.querySelectorAll('tbody tr[data-row]');
    var shown = 0;

    rows.forEach(function (row) {
        var matchText = row.textContent.toLowerCase().indexOf(text) !== -1;
        var matchClass = cls === '' || (row.dataset.class || '').toLowerCase() === cls;
        if (matchText && matchClass) {
            row.style.display = '';
            shown++;
        } else {
            row.style.display = 'none';
        }
    });

    if (rowCount) { rowCount.textContent = shown; }
}

if (searchBox) { searchBox.addEventListener('keyup', filterRows); }
if (classFilter) { classFilter.addEventListener('change', filterRows); }

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

// 6. Download the visible table rows as a CSV file (opens in Excel): <button id="exportCsv">
var exportBtn = document.getElementById('exportCsv');
if (exportBtn && dataTable) {
    exportBtn.addEventListener('click', function () {
        var lines = [];
        dataTable.querySelectorAll('tr').forEach(function (tr) {
            if (tr.style.display === 'none') { return; }
            var cells = [];
            tr.querySelectorAll('th, td').forEach(function (cell) {
                var text = cell.textContent.trim().replace(/\s+/g, ' ').replace(/"/g, '""');
                cells.push('"' + text + '"');
            });
            lines.push(cells.join(','));
        });
        var blob = new Blob([lines.join('\n')], { type: 'text/csv' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = exportBtn.dataset.filename || 'table.csv';
        link.click();
    });
}
