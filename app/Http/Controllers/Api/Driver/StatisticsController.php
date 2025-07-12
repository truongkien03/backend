<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    /**
     * Thống kê thu nhập và performance của shipper
     */
    public function shipperStatistics(Request $request)
    {
        $shipperId = auth('driver')->id();
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()->endOfMonth()));

        $shipper = auth('driver')->user();

        // Thống kê hiện tại
        $currentStats = Order::where('driver_id', $shipperId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                SUM(shipping_cost) as total_earnings,
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status_code = 4 THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status_code = 5 THEN 1 END) as cancelled_orders,
                AVG(driver_rate) as average_rating,
                SUM(distance) as total_distance
            ')
            ->first();

        // Thống kê tháng trước để so sánh
        $previousStartDate = $startDate->copy()->subMonth();
        $previousEndDate = $endDate->copy()->subMonth();
        
        $previousStats = Order::where('driver_id', $shipperId)
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->selectRaw('
                SUM(shipping_cost) as total_earnings,
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status_code = 4 THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status_code = 5 THEN 1 END) as cancelled_orders,
                AVG(driver_rate) as average_rating,
                SUM(distance) as total_distance
            ')
            ->first();

        // Thu nhập hàng ngày
        $dailyEarnings = Order::where('driver_id', $shipperId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                DATE(created_at) as date,
                SUM(shipping_cost) as earnings,
                COUNT(*) as orders,
                AVG(driver_rate) as rating,
                SUM(distance) as distance
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top khu vực giao hàng
        $topAreas = Order::where('driver_id', $shipperId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                JSON_EXTRACT(to_address, "$.desc") as area,
                COUNT(*) as orders,
                SUM(shipping_cost) as revenue
            ')
            ->groupBy('area')
            ->orderBy('revenue', 'desc')
            ->limit(5)
            ->get();

        // Tính commission (80% cho shipper)
        $commissionRate = 0.8;
        $currentCommission = ($currentStats->total_earnings ?? 0) * $commissionRate;
        $previousCommission = ($previousStats->total_earnings ?? 0) * $commissionRate;

        // Tính số giờ làm việc (giả sử 8 giờ/ngày)
        $workingDays = $startDate->diffInDays($endDate) + 1;
        $totalHours = $workingDays * 8;

        return response()->json([
            'success' => true,
            'data' => [
                'shipper_info' => [
                    'id' => $shipper->id,
                    'name' => $shipper->name,
                    'phone' => $shipper->phone_number,
                    'avatar' => $shipper->avatar,
                    'rating' => $shipper->review_rate,
                    'status' => $shipper->status
                ],
                'period' => [
                    'type' => $period,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d')
                ],
                'current_period' => [
                    'total_earnings' => $currentStats->total_earnings ?? 0,
                    'commission_earned' => $currentCommission,
                    'commission_rate' => $commissionRate,
                    'total_orders' => $currentStats->total_orders ?? 0,
                    'completed_orders' => $currentStats->completed_orders ?? 0,
                    'cancelled_orders' => $currentStats->cancelled_orders ?? 0,
                    'completion_rate' => $currentStats->total_orders ? round(($currentStats->completed_orders / $currentStats->total_orders) * 100, 1) : 0,
                    'average_rating' => round($currentStats->average_rating ?? 0, 1),
                    'total_distance' => round($currentStats->total_distance ?? 0, 1),
                    'total_hours' => $totalHours
                ],
                'previous_period' => [
                    'total_earnings' => $previousStats->total_earnings ?? 0,
                    'commission_earned' => $previousCommission,
                    'commission_rate' => $commissionRate,
                    'total_orders' => $previousStats->total_orders ?? 0,
                    'completed_orders' => $previousStats->completed_orders ?? 0,
                    'cancelled_orders' => $previousStats->cancelled_orders ?? 0,
                    'completion_rate' => $previousStats->total_orders ? round(($previousStats->completed_orders / $previousStats->total_orders) * 100, 1) : 0,
                    'average_rating' => round($previousStats->average_rating ?? 0, 1),
                    'total_distance' => round($previousStats->total_distance ?? 0, 1),
                    'total_hours' => $totalHours
                ],
                'growth' => [
                    'earnings_growth' => $previousStats->total_earnings ? round((($currentStats->total_earnings - $previousStats->total_earnings) / $previousStats->total_earnings) * 100, 1) : 0,
                    'orders_growth' => $previousStats->total_orders ? round((($currentStats->total_orders - $previousStats->total_orders) / $previousStats->total_orders) * 100, 1) : 0,
                    'rating_improvement' => round(($currentStats->average_rating - $previousStats->average_rating), 1)
                ],
                'daily_performance' => $dailyEarnings->map(function($day) {
                    return [
                        'date' => $day->date,
                        'earnings' => $day->earnings,
                        'orders' => $day->orders,
                        'rating' => round($day->rating ?? 0, 1),
                        'distance' => round($day->distance ?? 0, 1)
                    ];
                }),
                'top_areas' => $topAreas->map(function($area) {
                    return [
                        'area' => $area->area,
                        'orders' => $area->orders,
                        'revenue' => $area->revenue
                    ];
                }),
                'performance_metrics' => [
                    'average_order_value' => $currentStats->total_orders ? round($currentStats->total_earnings / $currentStats->total_orders) : 0,
                    'orders_per_day' => $workingDays ? round($currentStats->total_orders / $workingDays, 1) : 0,
                    'earnings_per_hour' => $totalHours ? round($currentCommission / $totalHours) : 0,
                    'earnings_per_order' => $currentStats->total_orders ? round($currentCommission / $currentStats->total_orders) : 0
                ]
            ]
        ]);
    }
} 