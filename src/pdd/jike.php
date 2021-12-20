<?php

namespace Greenhat616\ExpressApiProvider\PDD;

use Greenhat616\ExpressApiProvider\Client\Requests;
use Exception;

class Jike
{
    private string $app_key;
    private string $app_secret;
    private string $user_id;
    private string $shop_code;
    private string $api_endpoint = 'http://pddjk.qzzo.com';
    private Requests $client;

    /**
     * 初始化令牌信息
     */
    public function __construct(string $apiKey, string $appSecret, string $userID, string $shopCode)
    {
        $this->app_key = $apiKey ?? '';
        $this->app_secret = $appSecret ?? '';
        $this->user_id = $userID ?? '';
        $this->shop_code = $shopCode ?? "";
        $this->client = new Requests($this->api_endpoint); // 初始化请求库
    }

    /**
     * 获取店铺信息
     * @param string $ownerName
     * @return object|array
     * @throws Exception|GuzzleException
     */
    public function getShopInfo(string $ownerName)
    {
        $data = $this->generateAuthData();
        $data['ownerName'] = $ownerName;
        return $this->performAPIRequest('/api/opt/plat/shop/info', 'GET', $data);
    }

    /**
     * 生产验证数据
     * @return array
     * @throws Exception
     */
    private function generateAuthData(): array
    {
        if (!$this->app_key || !$this->app_secret) {
            throw new Exception('app_key 或 app_secret 未设置');
        }
        $sendTime = (int)(microtime(true) * 1000);
        return [
            'sendTime' => $sendTime,
            'appKey' => $this->app_key,
            'sign' => $this->getSign(strval($sendTime)),
        ];
    }

    /**
     * 生产签名
     * @param string $time
     * @return string
     */
    private function getSign(string $time): string
    {
        return strtoupper(md5(strval($time) . strval($this->app_secret)));
    }

    /**
     * 发起 API 请求
     * @param string $path
     * @param string $method
     * @param array|null $query
     * @param array|null $body
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    private function performAPIRequest(string $path, string $method, ?array $query = null, ?array $body = null)
    {
        $requestOptions = [
            'query' => $query,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36',
                'Accept' => 'application/json',
                'Accept-Encoding' => 'gzip,deflate,sdch',
                'Accept-Language' => 'zh-CN,zh;q=0.8',
            ],
        ];
        if ($body) {
            $requestOptions['form_params'] = $body;
        }
        // Log::info($query);
        // Log::info($body);
        $response = $this->client->getClient()->request($method, $path, $requestOptions);
        // 处理响应
        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();
        if ($statusCode !== 200) {
            // Log::error("请求失败, API 状态码{$statusCode}, 返回信息：$body");
            throw new Exception("请求失败, API 状态码{$statusCode}, 返回信息：$body");
        }
        $api_data = json_decode(json_decode($body)); // 我也不懂为啥这个接口要 decode 两次
        if (!$api_data) {
            // Log::error("解析响应失败。API 状态码$statusCode, 返回信息：{$body}");
            throw new Exception("解析响应失败。API 状态码$statusCode, 返回信息：{$body}");
        }
        if (isset($api_data->code) && $api_data->code !== 10000) {
            throw new Exception("请求失败，信息 $api_data->code : $api_data->message, 返回结果 " . json_decode($body));
        }
        // Log::debug($api_data);
        return $api_data->result;
    }

    /**
     * 获取退货地址
     * @param string $ownerId
     * @return object
     * @throws Exception|GuzzleException
     */
    public function getRefundAddress(string $ownerId)
    {
        $data = $this->generateAuthData();
        $data['ownerId'] = $ownerId;
        return $this->performAPIRequest('/api/opt/plat/shop/refund/address', 'GET', $data);
    }

    /**
     * 获取订单
     * @param string $ownerId
     * @param string $startTime
     * @param string $endTime
     * @param int $page
     * @param int $pageSize
     * @param string|null $remark
     * @param int|null $remarkTag
     * @param string|null $remarkTagName
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function getShopOrders(string $ownerId, string $startTime, string $endTime, int $page = 0, int $pageSize = 100, ?string $remark, ?int $remarkTag, ?string $remarkTagName)
    {
        $data = $this->generateAuthData();
        $data['ownerId'] = $ownerId;
        $data['startTime'] = $startTime;
        $data['endTime'] = $endTime;
        $data['page'] = $page;
        $data['pageSize'] = $pageSize;
        if ($remark) $data['remark'] = $remark;
        if ($remarkTag) $data['remarkTag'] = $remarkTag;
        if ($remarkTagName) $data['remarkTagName'] = $remarkTagName;
        return $this->performAPIRequest('/api/opt/plat/shop/orders', 'GET', $data);
    }

    /**
     * 获取订单详情
     * @param string $ownerId
     * @param string $orderSns
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function getShopOrdersInfo(string $ownerId, string $orderSns)
    {
        $data = $this->generateAuthData();
        $data['ownerId'] = $ownerId;
        $data['orderSns'] = $orderSns;
        return $this->performAPIRequest('/api/opt/plat/shop/orders/info', 'GET', $data);
    }

    /**
     * 出单
     * @param $params
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function getWayBill($params)
    {
        $auth = $this->generateAuthData();
        $data = [];
        $data['params'] = json_encode($params);
        return $this->performAPIRequest('/api/opt/plat/get_waybill_code/v2', 'POST', $auth, $data);
    }

    /**
     * 获取自由订单单号接口
     * @param $params
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function getFreeWayBill($params)
    {
        $auth = $this->generateAuthData();
        $data = [];
        $data['params'] = json_encode($params);
        return $this->performAPIRequest('/api/opt/plat/free/get_waybill_code/v2', 'POST', $auth, $data);
    }

    /**
     * 创建自由订单
     * @param string $ownerId
     * @param $params
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function createFreeOrder(string $ownerId, $params)
    {
        $auth = $this->generateAuthData();
        $data = [];
        $data['ownerId'] = $ownerId;
        $data['params'] = json_encode($params);
        return $this->performAPIRequest('/api/opt/plat/free/order/create', 'POST', $auth, $data);
    }

    /**
     * 批量获取自由订单单号接口
     * @param $params
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function getBatchFreeWayBill($params)
    {
        $auth = $this->generateAuthData();
        $data = [];
        $data['params'] = json_encode($params, JSON_UNESCAPED_UNICODE);
        return $this->performAPIRequest('/api/opt/plat/free/batch/get_waybill_code/v2', 'POST', $auth, $data);
    }

    /**
     * 单号回收
     * @param string $ownerId
     * @param string $wpCode
     * @param string $waybillCodes
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function cancelWaybill(string $ownerId, string $wpCode, string $waybillCodes)
    {
        $auth = $this->generateAuthData();
        $data = [];
        $data['ownerId'] = $ownerId;
        $data['wpCode'] = $wpCode;
        $data['waybillCodes'] = $waybillCodes;
        return $this->performAPIRequest('/api/opt/plat/waybill/cancel', 'POST', $auth, $data);
    }

    /**
     * 发货接口
     * @param $params
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function notifyPlatformOnline($params)
    {
        $auth = $this->generateAuthData();
        $data = [];
        $data['params'] = json_encode($params);
        return $this->performAPIRequest('/api/opt/plat/notify/online', 'POST', $auth, $data);
    }

    /**
     * 根据订单号同步接口
     * @param $params
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function syncOrderBySN($params)
    {
        $auth = $this->generateAuthData();
        $data = [];
        $data['params'] = json_encode($params);
        return $this->performAPIRequest('/api/opt/plat/sync/orderBySn', 'POST', $auth, $data);
    }

    /**
     * 获得店铺 OwnerID
     * @param string|null $userID
     * @return mixed
     * @throws Exception
     * @throws GuzzleException
     */
    public function getOwnerID(?string $userID = null)
    {
        if (!$userID) $userID = $this->user_id;
        // $cacheKey = 'jike_owner_id_' . $userID;
        // $ownerID = Cache::get($cacheKey);
        // if (!$ownerID) {
        $shopInfo = $this->getShopInfo($userID);
        if (!empty($shopInfo->ownerId)) {
            // Cache::set($cacheKey, $shopInfo->ownerId);
            $ownerID = $shopInfo->ownerId;
        }
        // }
        return $ownerID ?? 0;
    }

    /**
     * 取得 ShopCode
     * @return string
     */
    public function getShopCode(): string
    {
        return $this->shop_code;
    }
}
