<?php

namespace app\controller;

use GuzzleHttp\Client;

class Tools
{
    protected Client $client;

    protected array $items = [];

    private array $fixedGridSizes = [1, 2, 4, 6, 9];
    public function __construct()
    {
        $curlVersion = curl_version(); // 获取curl版本信息
        $http2Supported = isset($curlVersion['features']) && ($curlVersion['features'] & CURL_VERSION_HTTP2) !== 0; // 判断是否支持http2
        $httpVersion = $http2Supported ? 2.0 : 1.1;
        $this->client = new Client([
            'verify' => false,
            'version' => $httpVersion,
        ]);
        $this->items = $this->items('props', 'collection');
    }
    /**
     * 前端展示方法，使用流式输出纯文本，延迟基于品阶
     * @param int $times 抽奖次数
     * @param int $candidateCount 候选物品数量
     */
    public function index(int $times = 1, int $candidateCount = 3)
    {
        // 设置SSE流式输出头
        $response = \think\Response::create()->header([
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // 禁用Nginx缓冲
        ]);

        // 执行抽奖
        $results = $this->drawByGridAndGrade($times, $candidateCount);

        // 流式输出每个结果
        foreach ($results as $index => $item) {
            // 输出空白内容（抽奖中提示）
            $placeholderData = [
                'drawNumber' => $index + 1,
                'status' => 'pending',
                'message' => '抽奖中...'
            ];
            echo "event: draw\n";
            echo "data: " . json_encode($placeholderData) . "\n\n";
            ob_flush();
            flush();

            // 根据品阶设置延迟
            $grade = $item['grade'];
            $delay = match($grade) { // 根据自己需要调整延迟
                1 => 0.3,
                2 => 0.5,
                3 => 0.8,
                4 => 0.9,
                5 => 1.5,
                6 => 2.3,
            }; // 1s to 6s
            usleep((int)($delay * 1000000)); // 微秒

            // 输出最终结果
            $resultData = [
                'drawNumber' => $index + 1,
                'status' => 'complete',
                'item' => $item
            ];
            echo "event: draw\n";
            echo "data: " . json_encode($resultData) . "\n\n";
            ob_flush();
            flush();
        }

        // 结束事件流
        echo "event: end\n";
        echo "data: {}\n\n";
        ob_flush();
        flush();

        return $response;
    }


    /**
     * 分步抽奖：先随机物品，随机固定格子数，最后基于格子和品阶抽奖
     * @param int $times 抽奖次数
     * @param int $candidateCount 候选物品数量
     * @return array 抽奖结果
     */
    public function drawByGridAndGrade(int $times = 1, int $candidateCount = 3): array
    {
        $result = [];

        for ($i = 0; $i < $times; $i++) {
            // 步骤1：随机抽取候选物品
            $candidates = $this->getRandomCandidates($candidateCount);

            // 步骤2：随机选择固定格子数
            $randomGridSize = $this->fixedGridSizes[array_rand($this->fixedGridSizes)];

            // 步骤3：根据格子数和品阶抽取最终物品
            $probabilities = array_map(function ($item) use ($randomGridSize) {
                $area = $item['length'] * $item['width'];
                // 格子数越接近目标格子数，概率越高；品阶越高，概率越低
                $areaDiff = abs($area - $randomGridSize);
                $gradePenalty = max(1, $item['grade']); // 品阶惩罚，6品阶概率最低
                // 放大1000倍并取整，避免浮点数
                return (int)(1000 * 1000 / max(1, $areaDiff + 1) / $gradePenalty);
            }, $candidates);
            $totalProbability = array_sum($probabilities);

            if ($totalProbability <= 0) {
                continue; // 避免无效概率
            }

            $rand = mt_rand(1, $totalProbability);
            $currentProbability = 0;
            $selectedItem = null;

            foreach ($candidates as $index => $item) {
                $currentProbability += $probabilities[$index];
                if ($rand <= $currentProbability) {
                    $selectedItem = [
                        'id' => $item['id'],
                        'objectID' => $item['objectID'],
                        'objectName' => $item['objectName'],
                        'length' => $item['length'],
                        'width' => $item['width'],
                        'grade' => $item['grade'],
                        'weight' => $item['weight'],
                        'desc' => $item['desc'],
                        'pic' => $item['pic'],
                        'avgPrice' => $item['avgPrice'],
                    ];
                    break;
                }
            }

            if ($selectedItem) {
                $result[] = $selectedItem;
            }
        }
        return $result;
    }

    /**
     * 随机抽取候选物品
     * @param int $count 候选物品数量
     * @return array 候选物品列表
     */
    private function getRandomCandidates(int $count): array
    {
        $count = min($count, count($this->items)); // 确保不超过物品总数
        $indices = array_rand($this->items, $count);
        if (!is_array($indices)) {
            $indices = [$indices];
        }
        $candidates = [];
        foreach ($indices as $index) {
            $candidates[] = $this->items[$index];
        }
        return $candidates;
    }

    private function items(string $type = '', string $subType = ''): array
    {

        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 352143,
                'iSubChartId' => 352143,
                'sIdeToken' => 'YWRywA',
                'source' => 2,
                'method' => 'dfm/object.list',
                'param' => json_encode([
                    'primary' => $type,
                    'second' => $subType,
                    'objectID',
                ]),
            ],
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        return $data['jData']['data']['data']['list'];
    }
}