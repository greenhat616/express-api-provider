<?php

namespace Greenhat616\ExpressApiProvider\Taobao;

use Greenhat616\ExpressApiProvider\Client\Requests;
use Exception;

class Chaoneng
{
    private string $appid;
    private string $secret;
    private string $seller_id;
    private string $api_endpoint = "http://smart.koo49.com/v2/tb/";
    private Requests $client;
    /**
     * @param string $appid
     * @param string $secret
     * @param string $seller_id
     */
    public function __construct(string $appKey, $appSecret, $sellerId)
    {
        $this->appid = $appKey;
        $this->secret = $appSecret;
        $this->seller_id = $sellerId;
        $this->client = new Requests($this->api_endpoint);
    }

    public function getPrivateData()
    {
        return [
            "app_id" => $this->appid,
            "secret" => $this->secret,
            "seller_id" => $this->seller_id,
            "endpoint" => $this->api_endpoint,
        ];
    }

    /**
     * 生产签名
     * @param string $secret
     * @param string $appid
     * @param string $timestamps
     * @param string $nostr
     * @return string
     */
    private function getSign(
        string $secret,
        string $appid,
        string $timestamps,
        string $nostr
    ): string {
        return substr(
            md5(md5($secret . $appid . $timestamps . $nostr . $secret)),
            3,
            18
        );
    }

    /**
     * 生产验证数据
     * @return array
     * @throws Exception
     */
    private function generateAuthData(): array
    {
        if (!$this->appid || !$this->secret) {
            throw new Exception("appid 或 secret 未设置");
        }
        // 填充信息
        $ts = time();
        $nostr = random_bytes(20);
        // 生成数组
        $authData = [];
        $authData["appid"] = $this->appid;
        $authData["nostr"] = $nostr;
        $authData["timestamps"] = $ts;
        $authData["sign"] = $this->getSign(
            $this->secret,
            $this->appid,
            strval($ts),
            $nostr
        );
        return $authData;
    }

    /**
     * 发起 API 请求
     * @param string $path
     * @param string $method
     * @param array|null $query
     * @param array|null $body
     * @return object
     * @throws GuzzleException
     * @throws Exception
     */
    private function performAPIRequest(
        string $path,
        string $method,
        ?array $query = null,
        ?array $body = null
    ): object {
        $requestOptions = [
            "query" => $query,
            "headers" => [
                "User-Agent" =>
                    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36",
                "Accept" => "application/json",
                "Accept-Encoding" => "gzip,deflate,sdch",
                "Accept-Language" => "zh-CN,zh;q=0.8",
            ],
        ];
        if ($body) {
            $requestOptions["form_params"] = $body;
        }
        $response = $this->client
            ->getClient()
            ->request($method, $path, $requestOptions);
        // 处理响应
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        if ($statusCode !== 200) {
            throw new Exception(
                "请求失败, API 状态码{$statusCode}, 返回信息：{$body}"
            );
        }
        $api_data = json_decode($body);
        if (!$api_data) {
            throw new Exception(
                "解析响应失败。API 状态码{$statusCode}, 返回信息：{$body}"
            );
        }
        if ($api_data->code !== "2000") {
            throw new Exception(
                "API 响应错误，{$api_data->code}，{$api_data->msg}; \n 返回信息：{$body}"
            );
        }
        return $api_data;
    }

    /**
     * 获得授权 URL
     * @param string $callbackParams
     * @return string
     * @throws Exception
     * @throws GuzzleException
     */
    public function getAuthorizationURL(string $callbackParams): string
    {
        $callbackURL = "http://{$_SERVER["HTTP_HOST"]}/api/Auth/back.html"; // 通知地址（回调地址）
        $authData = $this->generateAuthData();
        $response = $this->performAPIRequest("getAuthUrl", "GET", $authData);
        return $response->data->tb_url .
            $callbackParams .
            ";back_" .
            $callbackURL;
    }

    /**
     * 获得店铺信息
     * @param string $sellerID 获取淘宝店铺信息
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function getShopInformation(string $sellerID): object
    {
        $data = $this->generateAuthData();
        $data["sellerId"] = $sellerID ?? $this->seller_id;
        return $this->performAPIRequest("getShopInfo", "POST", null, $data);
    }

    /**
     * 同步淘宝订单（使用分页）
     * @param string $sellerID
     * @param int|null $endTime
     * @param int|null $startTime
     * @param int|null $pageNo
     * @param int|null $pageSize
     * @param string|null $fields
     * @return object
     * @throws Exception|GuzzleException
     */
    public function syncOrderByPage(
        string $sellerID,
        ?int $endTime,
        ?int $startTime,
        ?int $pageNo,
        ?int $pageSize,
        ?string $fields = null
    ): object {
        $data = $this->generateAuthData();
        $data["sellerId"] = $sellerID ?? $this->seller_id;
        if ($endTime) {
            $data["endTime"] = $endTime;
        }
        if ($startTime) {
            $data["startTime"] = $startTime;
        }
        if ($pageNo) {
            $data["page_no"] = $pageNo;
        }
        if ($pageSize) {
            $data["page_size"] = $pageSize;
        }
        if ($fields) {
            $data["fields"] = $fields;
        }
        return $this->performAPIRequest(
            "syncTbOrderByPage",
            "POST",
            null,
            $data
        );
    }

    /**
     * 通过订单号同步订单
     * @param string $ownerID 店铺 ownerId
     * @param string[] $orderSns
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function syncOrderByOrderSN(string $ownerID, array $orderSns): object
    {
        $data = $this->generateAuthData();
        $data["owner_id"] = $ownerID;
        $data["orderSns"] = $orderSns;
        return $this->performAPIRequest(
            "syncTbOrderByOrderSn",
            "POST",
            null,
            $data
        );
    }

    /**
     * 淘宝出单
     * @param string $sellerID
     * @param $param
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function getWaybill(string $sellerID, $param): object
    {
        $data = $this->generateAuthData();
        $data["sellerId"] = $sellerID ?? $this->seller_id;
        $data["param"] = json_encode($param, JSON_UNESCAPED_UNICODE);
        return $this->performAPIRequest("getTbWaybill", "POST", null, $data);
    }

    /**
     * 淘宝批量发货
     * @param $params
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function shipMul($params): object
    {
        $data = $this->generateAuthData();
        $data["params"] = json_encode($params, JSON_UNESCAPED_UNICODE);
        return $this->performAPIRequest("tbShipMul", "POST", null, $data);
    }

    /**
     * 获取淘宝物流编码
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function getCpCode(): object
    {
        $data = $this->generateAuthData();
        return $this->performAPIRequest("getTbCpCode", "GET", $data);
    }

    /**
     * 获取淘宝电子面单
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    public function getExpTpl(): object
    {
        $data = $this->generateAuthData();
        return $this->performAPIRequest("getTbExpTpl", "GET", $data);
    }

    /**
     * 获得 ShopID
     * @param string|null $sellerID
     * @return string
     * @throws Exception
     * @throws GuzzleException
     */
    public function getShopID(?string $sellerID = null): string
    {
        if (!$sellerID) {
            $sellerID = $this->seller_id ?? "default";
        }
        $result = $this->getShopInformation($sellerID);
        // Log::info($result);
        $shopID = $result->data->sid ?? "";
        return $shopID;
    }
}
