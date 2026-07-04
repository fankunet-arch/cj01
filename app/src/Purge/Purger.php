<?php

declare(strict_types=1);

namespace Cj\Purge;

use Cj\Repository\CrawlerRepository;
use Cj\Repository\MainRepository;
use Cj\Support\Logger;

/**
 * 一键清理（文档 §8，“用完即删”的落地）：
 *  - main   ：以 cj_import_map 为账本精准清理主库（可按批次），origin='crawler' 双保险
 *  - all    ：清空全部采集数据（要求主库导入已先清理完 —— 顺序保护）
 *  - expired：按 purge_after 到期清理
 *  - site   ：按站清理
 * 顺序约束：必须先据 cj_import_map 处理完主库，再删采集库，否则账本先没了。
 */
final class Purger
{
    private CrawlerRepository $crawler;
    private MainRepository $main;

    public function __construct()
    {
        $this->crawler = new CrawlerRepository();
        $this->main = new MainRepository();
    }

    /** 精准清理主库导入记录（可按 import_batch），回写 purged 标记。 */
    public function purgeMain(?string $batch = null, bool $dryRun = false): array
    {
        if (!$this->main->enabled()) {
            throw new \RuntimeException('main.mode=off：清理主库需配置主库连接（config.php → main）');
        }
        $rows = $this->crawler->unpurgedImportMap($batch);
        $deleted = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if ($dryRun) {
                Logger::info('purge', sprintf('[dry-run] 将删除主库 #%d（批次 %s）', $row['main_job_id'], $row['import_batch']));
                continue;
            }
            // origin='crawler' 双保险：非采集来源绝不删除
            if ($this->main->deleteCrawlerJob((int) $row['main_job_id'])) {
                $this->crawler->markPurged((int) $row['id']);
                $deleted++;
            } else {
                $skipped++;
                Logger::error('purge', sprintf(
                    '主库 #%d 未删除（不存在或 origin 非 crawler），账本 #%d 保留待人工核查',
                    $row['main_job_id'], $row['id']
                ));
            }
        }
        Logger::info('purge', "主库清理：deleted=$deleted skipped=$skipped" . ($batch !== null ? "（批次 $batch）" : ''));
        return ['deleted' => $deleted, 'skipped' => $skipped, 'total' => count($rows)];
    }

    /** 清空全部采集数据。顺序保护：主库还有未清理的导入记录时拒绝执行。 */
    public function purgeAll(bool $force = false): void
    {
        if (!$force && $this->crawler->hasUnpurgedImports()) {
            throw new \RuntimeException(
                '主库尚有未清理的导入记录（cj_import_map.purged=0）。' .
                '先执行 purge.php --mode=main，账本处理完再清采集库（文档 §8 顺序约束）。' .
                '确要跳过请加 --force（将失去主库精准清理依据，只能靠 origin 粗粒度清理）。'
            );
        }
        $this->crawler->truncateAll();
        Logger::info('purge', '采集库已清空（全部 cj_ 表 TRUNCATE）');
    }

    /** 按 purge_after 到期清理。 */
    public function purgeExpired(): int
    {
        $n = $this->crawler->purgeExpired();
        Logger::info('purge', "到期清理：$n 条");
        return $n;
    }

    /** 按站清理。 */
    public function purgeSite(string $site): int
    {
        $n = $this->crawler->purgeSite($site);
        Logger::info('purge', "[$site] 按站清理：$n 条");
        return $n;
    }
}
