<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\Staging;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectStats extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        return [
            Stat::make('Projets', Project::count())
                ->description('Vue d’ensemble et maintenance')
                ->descriptionIcon('heroicon-o-square-3-stack-3d')
                ->url(ProjectResource::getUrl()),
            Stat::make('En maintenance', Project::whereNotNull('maintenance_yml_file')->where('maintenance_enabled', true)->count())
                ->description('Selon les dernières vérifications Traefik')
                ->color('warning')
                ->descriptionIcon('heroicon-o-wrench-screwdriver')
                ->url(ProjectResource::getUrl()),
            Stat::make('Stagings', Staging::count())
                ->description('Environnements de prévisualisation')
                ->descriptionIcon('heroicon-o-beaker')
                ->url(ProjectResource::getUrl()),
        ];
    }
}
