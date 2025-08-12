<?php

namespace Elastico\Checks;

use Exception;
use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Monitors Elasticsearch cluster health, node stats, and indexing performance.
 */
class ClusterCheck extends Check
{
    protected string $connection;

    public function connection(string $connection): static
    {
        $this->connection = $connection;
        $this->name($this->connection . ' Elasticsearch Cluster Health');

        return $this;
    }

    public function run(): Result
    {
        $result = Result::make();
        $client = DB::connection($this->connection)->getClient();

        try {
            // Fetch cluster-wide stats
            $clusterHealth = $client->cluster()->health()->asArray();
            $nodeStats = $client->nodes()->stats()->asArray();
            $indexStats = $client->indices()->stats()->asArray();
            $pendingTasks = $client->cluster()->pendingTasks()->asArray();
            $threadPool = $client->cat()->threadPool(['format' => 'json'])->asArray();

            $status = $clusterHealth['status']; // green, yellow, red
            $unassignedShards = $clusterHealth['unassigned_shards'] ?? 0;

            // Store the worst-case values across nodes
            $worstHeapUsed = 0;
            $worstCpuUsage = 0;
            $worstDiskUsage = 0;

            // Iterate over all nodes
            foreach ($nodeStats['nodes'] as $nodeId => $node) {
                $heapUsed = $node['jvm']['mem']['heap_used_percent'] ?? 0;
                $cpuUsage = $node['os']['cpu']['percent'] ?? 0;
                $diskAvailable = $node['fs']['total']['available_in_bytes'] ?? 0;
                $diskTotal = $node['fs']['total']['total_in_bytes'] ?? 1;
                $diskUsagePercent = 100 - (($diskAvailable / $diskTotal) * 100);

                // Keep track of the worst values among nodes
                $worstHeapUsed = max($worstHeapUsed, $heapUsed);
                $worstCpuUsage = max($worstCpuUsage, $cpuUsage);
                $worstDiskUsage = max($worstDiskUsage, $diskUsagePercent);
            }

            // Query & Indexing Performance
            $queryTime = $indexStats['indices']['_all']['search']['query_time_in_millis'] ?? 0;
            $queryCount = $indexStats['indices']['_all']['search']['query_total'] ?? 1;
            $avgQueryLatency = $queryCount ? ($queryTime / $queryCount) : 0;

            // Pending Tasks
            $pendingTasksCount = count($pendingTasks) ?? 0;

            // Thread Pool Rejections
            $searchQueue = collect($threadPool)->where('name', 'search')->first()['queue'] ?? 0;
            $bulkQueue = collect($threadPool)->where('name', 'bulk')->first()['queue'] ?? 0;

            // Build meta info
            $meta = [
                'cluster_status' => $status,
                'unassigned_shards' => $unassignedShards,
                'worst_heap_usage_percent' => $worstHeapUsed,
                'worst_cpu_usage_percent' => $worstCpuUsage,
                'worst_disk_usage_percent' => $worstDiskUsage,
                'avg_query_latency_ms' => $avgQueryLatency,
                'pending_tasks' => $pendingTasksCount,
                'search_queue' => $searchQueue,
                'bulk_queue' => $bulkQueue,
            ];

            $result->meta($meta);

            // 🚨 Critical Issues (Fails the check)
            if (
                $status === 'red' ||
                $worstHeapUsed > 90 ||
                $worstCpuUsage > 80 ||
                $worstDiskUsage > 90 ||
                $avgQueryLatency > 500 ||
                $pendingTasksCount > 10 ||
                $searchQueue > 50 ||
                $bulkQueue > 50
            ) {
                return $result->failed("Elasticsearch cluster has critical issues.");
            }

            // ⚠️ Warnings (Triggers a warning)
            if (
                $status === 'yellow' ||
                $worstHeapUsed > 80 ||
                $worstCpuUsage > 60 ||
                $worstDiskUsage > 80 ||
                $avgQueryLatency > 200 ||
                $pendingTasksCount > 5 ||
                $searchQueue > 20 ||
                $bulkQueue > 20
            ) {
                return $result->warning("Elasticsearch cluster has some warnings.");
            }

            return $result->ok("Elasticsearch cluster is healthy.");
        } catch (Exception $e) {
            return $result->failed("Elasticsearch check failed: " . $e->getMessage());
        }
    }
}
