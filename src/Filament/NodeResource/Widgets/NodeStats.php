<?php

namespace Elastico\Filament\NodeResource\Widgets;

use Elastico\Models\Node;
use Elastico\Models\Index;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Illuminate\Support\Number;

class NodeStats extends BaseWidget
{
    protected function getCards(): array
    {
        // $total_filesytem = Node::sum('filesystem_total');
        $total_filesytem = Node::sum('fs->total->total_in_bytes');
        // $used_filesytem = Node::sum('filesystem_used_bytes');
        $available_bytes = Node::sum('fs->total->available_in_bytes');
        $used_filesytem = $total_filesytem - $available_bytes;

        return [
            Stat::make('Total Nodes', Number::format(Node::count(), locale: app()->getLocale())),
            Stat::make('Filesystem', Number::fileSize($total_filesytem)),
            Stat::make('Used Filesystem', Number::fileSize($used_filesytem))
                ->description(Number::percentage($used_filesytem / $total_filesytem * 100) . ' used'),
            Stat::make('Free Filesystem', Number::fileSize($available_bytes)),
            Stat::make('Total Memory', Number::fileSize(Node::sum('memory_max_bytes'))),
            Stat::make('Memory', Number::percentage(Node::avg('memory_current_percent'))),
            Stat::make('CPU', Number::format(Node::avg('cpu_load_15m'), locale: app()->getLocale())),
        ];
    }
}
