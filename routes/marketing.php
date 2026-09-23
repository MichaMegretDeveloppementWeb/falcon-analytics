<?php

declare(strict_types=1);

use Falcon\Analytics\Http\Controllers\Marketing\AdDetailController;
use Falcon\Analytics\Http\Controllers\Marketing\AdsController;
use Falcon\Analytics\Http\Controllers\Marketing\CampaignDetailController;
use Falcon\Analytics\Http\Controllers\Marketing\CampaignsController;
use Falcon\Analytics\Http\Controllers\Marketing\MarketingDashboardController;
use Illuminate\Support\Facades\Route;

/*
 * The marketing screens · the second mount point of the administration area.
 *
 * **Same area, second mount point.** They share the area's shell and its name
 * prefix — a host mounting both wants one chrome around them — but they carry
 * their own address and their own guards, from their own config block, so a
 * host can put campaign and ad management behind another guard.
 *
 * **Their group is a sibling of the analytics one, never a child** · a nested
 * group concatenates the prefixes (`/admin/analytics/admin/marketing/…`) and
 * ACCUMULATES the middleware, so the screens would demand both guards at once.
 */

Route::get('/', MarketingDashboardController::class)->name('dashboard');
Route::get('/campaigns', CampaignsController::class)->name('campaigns');
Route::get('/campaigns/{campaign}', CampaignDetailController::class)->name('campaigns.show');
Route::get('/ads', AdsController::class)->name('ads');
Route::get('/ads/{ad}', AdDetailController::class)->name('ads.show');
