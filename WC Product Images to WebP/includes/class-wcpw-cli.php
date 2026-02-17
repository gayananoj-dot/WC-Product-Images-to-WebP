<?php
namespace WCPW;

if (!defined('ABSPATH')) exit;

class CLI {
  public static function init(): void {
    \WP_CLI::add_command('wcpw convert', [__CLASS__, 'convert']);
  }

  public static function convert($args, $assoc_args): void {
    $batch = isset($assoc_args['batch']) ? max(1, (int)$assoc_args['batch']) : 50;

    $offset = 0;
    $totalConverted = 0; $totalSkipped = 0; $totalErrors = 0;

    while (true) {
      $product_ids = get_posts([
        'post_type' => 'product',
        'post_status' => ['publish', 'private', 'draft', 'pending'],
        'fields' => 'ids',
        'posts_per_page' => $batch,
        'offset' => $offset,
        'orderby' => 'ID',
        'order' => 'ASC',
      ]);

      if (!$product_ids) break;

      foreach ($product_ids as $pid) {
        $img_ids = Converter::get_product_image_ids((int)$pid);
        foreach ($img_ids as $aid) {
          $res = Converter::convert_attachment_to_webp((int)$aid, [
            'quality' => 82,
            'keep_original' => true,
          ]);
          if (!empty($res['ok']) && empty($res['skipped'])) $totalConverted++;
          elseif (!empty($res['skipped'])) $totalSkipped++;
          else $totalErrors++;
        }
      }

      $offset += count($product_ids);
      \WP_CLI::log("Processed {$offset} products. converted={$totalConverted}, skipped={$totalSkipped}, errors={$totalErrors}");
      if (count($product_ids) < $batch) break;
    }

    \WP_CLI::success("Done. converted={$totalConverted}, skipped={$totalSkipped}, errors={$totalErrors}");
  }
}
