jQuery(function ($) {
  const $btn = $("#wcpw-start");
  const $log = $("#wcpw-log");

  function log(line) {
    $log.append(line + "\n");
    $log.scrollTop($log[0].scrollHeight);
  }

  async function run() {
    let offset = 0;
    let totalConverted = 0, totalSkipped = 0, totalErrors = 0;

    $btn.prop("disabled", true);
    log("Starting bulk conversion...");

    while (true) {
      const res = await $.post(WCPW.ajaxUrl, {
        action: "wcpw_bulk_convert",
        nonce: WCPW.nonce,
        offset,
        limit: WCPW.batchSize,
      });

      if (!res || !res.success) {
        log("ERROR: " + (res?.data?.message || "Unknown error"));
        break;
      }

      const c = res.data.counts;
      totalConverted += c.converted;
      totalSkipped += c.skipped;
      totalErrors += c.errors;

      log(
        `Batch: products=${res.data.processedProducts}, converted=${c.converted}, skipped=${c.skipped}, errors=${c.errors}`
      );

      offset = res.data.nextOffset;
      if (res.data.done) {
        log("DONE.");
        log(`Totals: converted=${totalConverted}, skipped=${totalSkipped}, errors=${totalErrors}`);
        break;
      }
    }

    $btn.prop("disabled", false);
  }

  $btn.on("click", run);
});
