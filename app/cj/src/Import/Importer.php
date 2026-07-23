<?php

declare(strict_types=1);

namespace Cj\Import;

use Cj\Repository\CrawlerRepository;
use Cj\Repository\MainRepository;
use Cj\Support\Logger;

/**
 * 冷启动导入（文档 §4.5、§5.6）：
 * 人工确认后，把 import_ready=1 的记录批量导入 zhaopin 主库。
 * 每导入一条 → cj_import_map 记录 (crawler_job_id ↔ main_job_id, batch)，
 * 主库对应记录 origin='crawler'。导入保持人工把关，不做全自动。
 */
final class Importer
{
    private CrawlerRepository $crawler;
    private MainRepository $main;

    public function __construct()
    {
        $this->crawler = new CrawlerRepository();
        $this->main = new MainRepository();
    }

    /**
     * 执行一批导入，返回 ['batch' => string, 'imported' => int]。
     * $dryRun=true 时只列出将导入的记录，不写任何库。
     */
    public function run(int $limit = 200, bool $dryRun = false): array
    {
        if (!$this->main->enabled()) {
            throw new \RuntimeException('main.mode=off：导入需配置主库连接（config.php → main）');
        }

        $jobs = $this->crawler->importReadyJobs($limit);
        $batch = date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

        if ($dryRun) {
            foreach ($jobs as $job) {
                Logger::info('import', sprintf('[dry-run] #%d [%s] %s', $job['id'], $job['source_site'], $job['title'] ?? '(无标题)'));
            }
            return ['batch' => $batch, 'imported' => count($jobs)];
        }

        $imported = 0;
        foreach ($jobs as $job) {
            // MainRepository 会把这些字段映射到 zhaopin_posts 真实结构
            // （标题/公司/薪资/描述合并进 content，城市/分类按名称解析为 region_id/category_id）
            $mainId = $this->main->insertJob([
                'title'          => $job['title'],
                'company'        => $job['company'],
                'category'       => $job['category'],
                'city'           => $job['city'],
                'district'       => $job['district'],
                'salary_raw'     => $job['salary_raw'],
                'description'    => $job['description'],
                'contact_phone'  => $job['contact_phone'],
                'phone_norm'     => $job['phone_norm'],
                'contact_wechat' => $job['contact_wechat'],
                'contact_name'   => $job['contact_name'],
                'simhash'        => $job['simhash'],   // DB 取出即无符号十进制
            ]);
            // 账本存采集库：一一对应，防重复导入（uk_main）
            $this->crawler->recordImport((int) $job['id'], $mainId, $batch);
            $imported++;
        }

        Logger::info('import', "批次 $batch 导入完成：$imported 条");
        return ['batch' => $batch, 'imported' => $imported];
    }
}
