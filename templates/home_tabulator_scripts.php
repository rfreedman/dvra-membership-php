<?php
/** @var string $membersJson — json_encode output; embed only from server */
/** @var string $sort_by */
/** @var string $sort_dir */
/** @var string $base */
/** @var string $listParamsForExportJson */
$js = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$listParamsBase = $listParamsForExportJson ?? '{}';
?>
<script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.2/dist/js/tabulator.min.js"></script>
<script>
(function () {
  const MEMBERS_DATA = <?= $membersJson ?>;
  const SERVER_SORT_BY = "<?= $js((string) $sort_by) ?>";
  const SERVER_SORT_DIR = "<?= $js((string) $sort_dir) ?>";
  const APP_BASE = "<?= $js((string) ($base ?? "")) ?>";
  const LIST_PARAMS_BASE = <?= $listParamsBase ?>;

  const dash = "—";
  const form = document.getElementById("members-filter-form");
  const currentOnlyField = document.getElementById("members-current-only-field");
  const currentOnlyCb = document.getElementById("members-current-only-cb");

  function syncCurrentOnlyHiddenFromCheckbox() {
    currentOnlyField.value = currentOnlyCb.checked ? "yes" : "no";
  }

  function mergeListParamsWithLiveGrid() {
    const p = {};
    Object.assign(p, LIST_PARAMS_BASE);
    const sorters =
      typeof table.getSorters === "function"
        ? table.getSorters()
        : [];
    if (sorters && sorters.length > 0) {
      p.sort_by = sorters[0].field;
      p.sort_dir = sorters[0].dir;
    } else {
      p.sort_by = SERVER_SORT_BY;
      p.sort_dir = SERVER_SORT_DIR;
    }
    syncCurrentOnlyHiddenFromCheckbox();
    p.current_only = currentOnlyField.value || "yes";
    return p;
  }

  function buildMembersExportSearchParams(p) {
    const q = new URLSearchParams();
    q.set("sort_by", String(p.sort_by || ""));
    q.set("sort_dir", String(p.sort_dir || ""));
    q.set("current_only", p.current_only === "no" ? "no" : "yes");
    q.set("search", p.search !== undefined ? String(p.search) : "");
    q.set("arrl", p.arrl !== undefined ? String(p.arrl) : "");
    q.set("has_key", p.has_key !== undefined ? String(p.has_key) : "");
    if (
      Object.prototype.hasOwnProperty.call(p, "membership_type_id") &&
      p.membership_type_id !== undefined &&
      p.membership_type_id !== "" &&
      p.membership_type_id !== null
    ) {
      q.set(
        "membership_type_id",
        String(Number.isFinite(Number(p.membership_type_id)) ? Number(p.membership_type_id) : p.membership_type_id)
      );
    }
    return q;
  }

  function syncExportLinks() {
    const p = mergeListParamsWithLiveGrid();
    const qs = buildMembersExportSearchParams(p).toString();
    const suffix = qs !== "" ? "?" + qs : "";
    const paths = ["xlsx", "csv", "pdf"];
    paths.forEach((ext) => {
      const link = document.getElementById("members-export-link-" + ext);
      if (!link) {
        return;
      }
      link.href = APP_BASE + "/members/export." + ext + suffix;
    });
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
        minWidth: 180,
        cssClass: "members-tabulator-actions",
      },
      {
        title: "Call sign",
        field: "call_sign",
        sorter: "string",
        minWidth: 90,
        formatter: (cell) => (cell.getValue() ? cell.getValue() : dash),
      },
      { title: "Last name", field: "last_name", sorter: "string", minWidth: 100 },
      { title: "First name", field: "first_name", sorter: "string", minWidth: 100 },
      { title: "Email", field: "email", sorter: "string", minWidth: 140 },
      { title: "Phone", field: "phone", sorter: "string", minWidth: 100 },
      {
        title: "Street",
        field: "address_street",
        sorter: "string",
        minWidth: 120,
        formatter: (cell) => (cell.getValue() ? cell.getValue() : dash),
      },
      {
        title: "City",
        field: "address_city",
        sorter: "string",
        minWidth: 96,
        formatter: (cell) => (cell.getValue() ? cell.getValue() : dash),
      },
      {
        title: "St",
        field: "address_state",
        sorter: "string",
        width: 48,
        hozAlign: "center",
        formatter: (cell) => (cell.getValue() ? cell.getValue() : dash),
      },
      {
        title: "ZIP",
        field: "address_zip",
        sorter: "string",
        width: 88,
        formatter: (cell) => (cell.getValue() ? cell.getValue() : dash),
      },
      { title: "License class", field: "license_class", sorter: "string", minWidth: 110 },
      { title: "Membership type", field: "membership_type", sorter: "string", minWidth: 120 },
      {
        title: "ARRL",
        field: "arrl_member",
        sorter: "boolean",
        hozAlign: "center",
        width: 72,
        formatter: (cell) => (cell.getValue() ? "yes" : "no"),
      },
      {
        title: "Key #",
        field: "key_number",
        sorter: "number",
        hozAlign: "right",
        width: 72,
        formatter: (cell) => (cell.getValue() === null || cell.getValue() === undefined ? "" : String(cell.getValue())),
      },
      {
        title: "Paid through",
        field: "paid_through",
        sorter: "string",
        minWidth: 110,
        formatter: (cell) => cell.getValue() || "",
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
      syncExportLinks();
    });
  }
  syncExportLinks();
})();
</script>
