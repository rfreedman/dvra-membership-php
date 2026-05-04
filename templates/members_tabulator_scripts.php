<?php
/** @var string $membersJson — json_encode output; embed only from server */
/** @var string $sort_by */
/** @var string $sort_dir */
/** @var string $base */
/** @var string $membersSortTouchUrl */
$js = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.2/dist/js/tabulator.min.js"></script>
<script>
(function () {
  const MEMBERS_DATA = <?= $membersJson ?>;
  const SERVER_SORT_BY = "<?= $js((string) $sort_by) ?>";
  const SERVER_SORT_DIR = "<?= $js((string) $sort_dir) ?>";
  const APP_BASE = "<?= $js((string) ($base ?? "")) ?>";
  const MEMBERS_SORT_TOUCH_URL = "<?= $js((string) ($membersSortTouchUrl ?? "")) ?>";

  const dash = "—";
  const form = document.getElementById("members-filter-form");
  const currentOnlyField = document.getElementById("members-current-only-field");
  const currentOnlyCb = document.getElementById("members-current-only-cb");

  function syncCurrentOnlyHiddenFromCheckbox() {
    currentOnlyField.value = currentOnlyCb.checked ? "yes" : "no";
  }

  function syncExportLinks() {
    ["xlsx", "csv", "pdf"].forEach(function (ext) {
      const link = document.getElementById("members-export-link-" + ext);
      if (link) {
        link.href = APP_BASE + "/members/export." + ext;
      }
    });
  }

  async function persistClientSortThenSyncExports() {
    const sorters =
      typeof table.getSorters === "function" ? table.getSorters() : [];
    if (!sorters || sorters.length === 0 || !MEMBERS_SORT_TOUCH_URL) {
      syncExportLinks();
      return;
    }
    const body = new URLSearchParams();
    body.set("sort_by", String(sorters[0].field));
    body.set("sort_dir", String(sorters[0].dir));
    try {
      await fetch(MEMBERS_SORT_TOUCH_URL, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
        credentials: "same-origin",
      });
    } catch (e) {
      /* non-fatal */
    }
    syncExportLinks();
  }

  const table = new Tabulator("#members-grid", {
    data: MEMBERS_DATA,
    layout: "fitColumns",
    height: "100%",
    placeholder: "No members match the current filters.",
    columns: [
      {
        title: "Actions",
        field: "actions_html",
        sortable: false,
        headerSort: false,
        formatter: "html",
        hozAlign: "left",
        width: 124,
        cssClass: "members-tabulator-actions",
      },
      {
        /* ~40% narrower than 151px; typical FCC call fits */
        title: "Call sign",
        field: "call_sign",
        sorter: "string",
        width: 91,
        formatter: (cell) => (cell.getValue() ? cell.getValue() : dash),
      },
      {
        title: "Last name",
        field: "last_name",
        sorter: "string",
        width: 151,
      },
      {
        title: "First name",
        field: "first_name",
        sorter: "string",
        width: 161,
      },
      {
        title: "Paid through",
        field: "paid_through",
        sorter: "string",
        width: 125,
        cssClass: "members-col-fixed-data",
        formatter: (cell) => cell.getValue() || "",
      },
      {
        title: "Email",
        field: "email",
        sorter: "string",
        width: 230,
      },
      {
        title: "Phone",
        field: "phone",
        sorter: "string",
        width: 130,
        cssClass: "members-col-fixed-data",
      },
      {
        title: "Street",
        field: "address_street",
        sorter: "string",
        width: 170,
        formatter: (cell) => (cell.getValue() ? cell.getValue() : dash),
      },
      {
        title: "City",
        field: "address_city",
        sorter: "string",
        width: 98,
        formatter: (cell) => (cell.getValue() ? cell.getValue() : dash),
      },
      {
        title: "State",
        field: "address_state",
        sorter: "string",
        width: 95,
        cssClass: "members-col-fixed-data",
        hozAlign: "center",
        formatter: (cell) => (cell.getValue() ? cell.getValue() : dash),
      },
      {
        title: "ZIP",
        field: "address_zip",
        sorter: "string",
        width: 114,
        cssClass: "members-col-fixed-data",
        formatter: (cell) => (cell.getValue() ? cell.getValue() : dash),
      },
      {
        /* Was 193px header heuristic; −40px total from that */
        title: "License class",
        field: "license_class",
        sorter: "string",
        width: 153,
        cssClass: "members-col-ref-data",
      },
      {
        /* initial width: 15 chars @ 8px + 56px sortable header chrome (title is 15 chars) */
        title: "Membership type",
        field: "membership_type",
        sorter: "string",
        width: 176,
        cssClass: "members-col-ref-data",
      },
      {
        /* initial width: 4 chars ("ARRL") @ 8px + 56px sortable header chrome */
        title: "ARRL",
        field: "arrl_member",
        sorter: "boolean",
        width: 88,
        cssClass: "members-col-ref-data",
        hozAlign: "center",
        formatter: (cell) => (cell.getValue() ? "yes" : "no"),
      },
    ],
    initialSort: [{ column: SERVER_SORT_BY, dir: SERVER_SORT_DIR }],
  });

  form.addEventListener("submit", function () {
    syncCurrentOnlyHiddenFromCheckbox();
    const sorters = table.getSorters();
    if (sorters && sorters.length > 0) {
      form.querySelector('[name="sort_by"]').value = sorters[0].field;
      form.querySelector('[name="sort_dir"]').value = sorters[0].dir;
    }
    syncExportLinks();
  });

  currentOnlyCb.addEventListener("change", function () {
    syncCurrentOnlyHiddenFromCheckbox();
    form.requestSubmit();
  });

  function redrawMembersGrid() {
    table.redraw(true);
  }
  window.addEventListener("resize", redrawMembersGrid);
  window.requestAnimationFrame(redrawMembersGrid);

  if (typeof table.on === "function") {
    table.on("dataSorted", function () {
      const sorters = table.getSorters ? table.getSorters() : [];
      if (sorters && sorters.length > 0) {
        const sb = form.querySelector('[name="sort_by"]');
        const sd = form.querySelector('[name="sort_dir"]');
        if (sb) {
          sb.value = sorters[0].field;
        }
        if (sd) {
          sd.value = sorters[0].dir;
        }
      }
      void persistClientSortThenSyncExports();
    });
  }
  syncExportLinks();
})();
</script>
