<?php

class PixPayload
{
    /**
     * IDs do Payload do Pix
     * @var string
     */
    const ID_PAYLOAD_FORMAT_INDICATOR = '00';
    const ID_MERCHANT_ACCOUNT_INFORMATION = '26';
    const ID_MERCHANT_ACCOUNT_INFORMATION_GUI = '00';
    const ID_MERCHANT_ACCOUNT_INFORMATION_KEY = '01';
    const ID_MERCHANT_ACCOUNT_INFORMATION_DESCRIPTION = '02';
    const ID_MERCHANT_CATEGORY_CODE = '52';
    const ID_TRANSACTION_CURRENCY = '53';
    const ID_TRANSACTION_AMOUNT = '54';
    const ID_COUNTRY_CODE = '58';
    const ID_MERCHANT_NAME = '59';
    const ID_MERCHANT_CITY = '60';
    const ID_ADDITIONAL_DATA_FIELD_TEMPLATE = '62';
    const ID_ADDITIONAL_DATA_FIELD_TXID = '05';
    const ID_CRC16 = '63';

    /**
     * Chave Pix
     * @var string
     */
    private $pixKey;

    /**
     * Descrição do pagamento (opcional)
     * @var string
     */
    private $description;

    /**
     * Nome do titular da conta
     * @var string
     */
    private $merchantName;

    /**
     * Cidade do titular da conta
     * @var string
     */
    private $merchantCity;

    /**
     * ID da transação (TxID)
     * @var string
     */
    private $txid;

    /**
     * Valor da transação
     * @var string
     */
    private $amount;

    /**
     * Define a chave pix
     * @param string $pixKey
     * @return PixPayload
     */
    public function setPixKey($pixKey)
    {
        $this->pixKey = $pixKey;
        return $this;
    }

    /**
     * Define a descrição do pagamento
     * @param string $description
     * @return PixPayload
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Define o nome do titular da conta
     * @param string $merchantName
     * @return PixPayload
     */
    public function setMerchantName($merchantName)
    {
        $this->merchantName = $merchantName;
        return $this;
    }

    /**
     * Define a cidade do titular da conta
     * @param string $merchantCity
     * @return PixPayload
     */
    public function setMerchantCity($merchantCity)
    {
        $this->merchantCity = $merchantCity;
        return $this;
    }

    /**
     * Define o ID da transação
     * @param string $txid
     * @return PixPayload
     */
    public function setTxid($txid)
    {
        $this->txid = $txid;
        return $this;
    }

    /**
     * Define o valor da transação
     * @param float $amount
     * @return PixPayload
     */
    public function setAmount($amount)
    {
        $this->amount = number_format($amount, 2, '.', '');
        return $this;
    }

    /**
     * Remove acentos e caracteres especiais
     * @param string $string
     * @return string
     */
    private function sanitize($string)
    {
        $string = preg_replace(array("/(á|à|ã|â|ä)/","/(Á|À|Ã|Â|Ä)/","/(é|è|ê|ë)/","/(É|È|Ê|Ë)/","/(í|ì|î|ï)/","/(Í|Ì|Î|Ï)/","/(ó|ò|õ|ô|ö)/","/(Ó|Ò|Õ|Ô|Ö)/","/(ú|ù|û|ü)/","/(Ú|Ù|Û|Ü)/","/(ñ)/","/(Ñ)/","/(ç)/","/(Ç)/"),explode(" ","a A e E i I o O u U n N c C"),$string);
        return strtoupper(preg_replace('/[^a-zA-Z0-9 ]/', '', $string)); // Apenas letras, números e espaços
    }

    /**
     * Responsável por retornar o valor completo de um objeto do payload
     * @param string $id
     * @param string $value
     * @return string
     */
    private function getValue($id, $value)
    {
        $value = substr($value, 0, 99); // Limita tamanho por segurança EMV
        $size = str_pad(strlen($value), 2, '0', STR_PAD_LEFT);
        return $id . $size . $value;
    }

    /**
     * Retorna os dados da conta merchant
     * @return string
     */
    private function getMerchantAccountInformation()
    {
        // Domínio do Banco Central
        $gui = $this->getValue(self::ID_MERCHANT_ACCOUNT_INFORMATION_GUI, 'br.gov.bcb.pix');
        // Chave Pix
        $key = $this->getValue(self::ID_MERCHANT_ACCOUNT_INFORMATION_KEY, $this->pixKey);
        // Descrição (se houver) - Sanitizada
        $desc = $this->description ? $this->sanitize($this->description) : '';
        $description = strlen($desc) ? $this->getValue(self::ID_MERCHANT_ACCOUNT_INFORMATION_DESCRIPTION, $desc) : '';

        return $this->getValue(self::ID_MERCHANT_ACCOUNT_INFORMATION, $gui . $key . $description);
    }

    /**
     * Retorna os dados adicionais do pix (TxID)
     * @return string
     */
    private function getAdditionalDataFieldTemplate()
    {
        // TxID (se vazio usa ***)
        $txid = $this->getValue(self::ID_ADDITIONAL_DATA_FIELD_TXID, strlen($this->txid) ? $this->txid : '***');
        return $this->getValue(self::ID_ADDITIONAL_DATA_FIELD_TEMPLATE, $txid);
    }

    /**
     * Calcula o CRC16 dos dados (Polinômio 0x1021)
     * @param string $payload
     * @return string
     */
    private function getCRC16($payload)
    {
        $payload .= self::ID_CRC16 . '04';

        $polinomio = 0x1021;
        $resultado = 0xFFFF;

        if (($length = strlen($payload)) > 0) {
            for ($offset = 0; $offset < $length; $offset++) {
                $resultado ^= (ord($payload[$offset]) << 8);
                for ($bitwise = 0; $bitwise < 8; $bitwise++) {
                    if (($resultado <<= 1) & 0x10000) $resultado ^= $polinomio;
                    $resultado &= 0xFFFF;
                }
            }
        }

        return self::ID_CRC16 . '04' . strtoupper(str_pad(dechex($resultado), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Gera o código completo do payload Pix
     * @return string
     */
    public function getPayload()
    {
        $name = $this->sanitize($this->merchantName);
        $city = $this->sanitize($this->merchantCity);

        $payload = $this->getValue(self::ID_PAYLOAD_FORMAT_INDICATOR, '01') .
                   $this->getMerchantAccountInformation() .
                   $this->getValue(self::ID_MERCHANT_CATEGORY_CODE, '0000') .
                   $this->getValue(self::ID_TRANSACTION_CURRENCY, '986') .
                   $this->getValue(self::ID_TRANSACTION_AMOUNT, $this->amount) .
                   $this->getValue(self::ID_COUNTRY_CODE, 'BR') .
                   $this->getValue(self::ID_MERCHANT_NAME, $name) .
                   $this->getValue(self::ID_MERCHANT_CITY, $city) .
                   $this->getAdditionalDataFieldTemplate();

        return $payload . $this->getCRC16($payload);
    }
}