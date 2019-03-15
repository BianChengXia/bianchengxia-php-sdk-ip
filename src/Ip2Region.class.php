<?php

/**
 * IP查询类
 */

defined('INDEX_BLOCK_LENGTH') or define('INDEX_BLOCK_LENGTH', 12);
defined('TOTAL_HEADER_LENGTH') or define('TOTAL_HEADER_LENGTH', 8192);

class Ip2Region
{
    private $dbFileHandler = NULL;
    private $HeaderSip = NULL;
    private $HeaderPtr = NULL;
    private $headerLen = 0;
    private $firstIndexPtr = 0;
    private $lastIndexPtr = 0;
    private $totalBlocks = 0;
    private $dbBinStr = NULL;
    private $dbFile = NULL;

    public function __construct($ip2regionFile = null)
    {
        $this->dbFile = is_null($ip2regionFile) ? __DIR__ . '/data/ip2region.db' : $ip2regionFile;
    }

    public function memorySearch($ip)
    {
        if ($this->dbBinStr == NULL) {
            $this->dbBinStr = file_get_contents($this->dbFile);
            if ($this->dbBinStr == false) {
                throw new Exception("Fail to open the db file {$this->dbFile}");
            }
            $this->firstIndexPtr = self::getLong($this->dbBinStr, 0);
            $this->lastIndexPtr = self::getLong($this->dbBinStr, 4);
            $this->totalBlocks = ($this->lastIndexPtr - $this->firstIndexPtr) / INDEX_BLOCK_LENGTH + 1;
        }
        if (is_string($ip)) $ip = self::safeIp2long($ip);
        $l = 0;
        $h = $this->totalBlocks;
        $dataPtr = 0;
        while ($l <= $h) {
            $m = (($l + $h) >> 1);
            $p = $this->firstIndexPtr + $m * INDEX_BLOCK_LENGTH;
            $sip = self::getLong($this->dbBinStr, $p);
            if ($ip < $sip) {
                $h = $m - 1;
            } else {
                $eip = self::getLong($this->dbBinStr, $p + 4);
                if ($ip > $eip) {
                    $l = $m + 1;
                } else {
                    $dataPtr = self::getLong($this->dbBinStr, $p + 8);
                    break;
                }
            }
        }
        if ($dataPtr == 0) return NULL;
        $dataLen = (($dataPtr >> 24) & 0xFF);
        $dataPtr = ($dataPtr & 0x00FFFFFF);
        /*return array(
            'city_id' => self::getLong($this->dbBinStr, $dataPtr),
            'region' => substr($this->dbBinStr, $dataPtr + 4, $dataLen - 4)
        );*/
        return substr($this->dbBinStr, $dataPtr + 4, $dataLen - 4);
    }

    public function binarySearch($ip)
    {
        if (is_string($ip)) $ip = self::safeIp2long($ip);
        if ($this->totalBlocks == 0) {
            if ($this->dbFileHandler == NULL) {
                $this->dbFileHandler = fopen($this->dbFile, 'r');
                if ($this->dbFileHandler == false) {
                    throw new Exception("Fail to open the db file {$this->dbFile}");
                }
            }
            fseek($this->dbFileHandler, 0);
            $superBlock = fread($this->dbFileHandler, 8);
            $this->firstIndexPtr = self::getLong($superBlock, 0);
            $this->lastIndexPtr = self::getLong($superBlock, 4);
            $this->totalBlocks = ($this->lastIndexPtr - $this->firstIndexPtr) / INDEX_BLOCK_LENGTH + 1;
        }
        $l = 0;
        $h = $this->totalBlocks;
        $dataPtr = 0;
        while ($l <= $h) {
            $m = (($l + $h) >> 1);
            $p = $m * INDEX_BLOCK_LENGTH;
            fseek($this->dbFileHandler, $this->firstIndexPtr + $p);
            $buffer = fread($this->dbFileHandler, INDEX_BLOCK_LENGTH);
            $sip = self::getLong($buffer, 0);
            if ($ip < $sip) {
                $h = $m - 1;
            } else {
                $eip = self::getLong($buffer, 4);
                if ($ip > $eip) {
                    $l = $m + 1;
                } else {
                    $dataPtr = self::getLong($buffer, 8);
                    break;
                }
            }
        }
        if ($dataPtr == 0) return NULL;
        $dataLen = (($dataPtr >> 24) & 0xFF);
        $dataPtr = ($dataPtr & 0x00FFFFFF);

        fseek($this->dbFileHandler, $dataPtr);
        $data = fread($this->dbFileHandler, $dataLen);

        /*return array(
            'city_id' => self::getLong($data, 0),
            'region' => substr($data, 4)
        );*/
        return substr($data, 4);
    }

    public function btreeSearch($ip)
    {
        if (is_string($ip)) $ip = self::safeIp2long($ip);
        if ($this->HeaderSip == NULL) {
            if ($this->dbFileHandler == NULL) {
                $this->dbFileHandler = fopen($this->dbFile, 'r');
                if ($this->dbFileHandler == false) {
                    throw new Exception("Fail to open the db file {$this->dbFile}");
                }
            }
            fseek($this->dbFileHandler, 8);
            $buffer = fread($this->dbFileHandler, TOTAL_HEADER_LENGTH);
            $idx = 0;
            $this->HeaderSip = array();
            $this->HeaderPtr = array();
            for ($i = 0; $i < TOTAL_HEADER_LENGTH; $i += 8) {
                $startIp = self::getLong($buffer, $i);
                $dataPtr = self::getLong($buffer, $i + 4);
                if ($dataPtr == 0) break;

                $this->HeaderSip[] = $startIp;
                $this->HeaderPtr[] = $dataPtr;
                $idx++;
            }

            $this->headerLen = $idx;
        }
        $l = 0;
        $h = $this->headerLen;
        $sptr = 0;
        $eptr = 0;
        while ($l <= $h) {
            $m = (($l + $h) >> 1);
            if ($ip == $this->HeaderSip[$m]) {
                if ($m > 0) {
                    $sptr = $this->HeaderPtr[$m - 1];
                    $eptr = $this->HeaderPtr[$m];
                } else {
                    $sptr = $this->HeaderPtr[$m];
                    $eptr = $this->HeaderPtr[$m + 1];
                }
                break;
            }
            if ($ip < $this->HeaderSip[$m]) {
                if ($m == 0) {
                    $sptr = $this->HeaderPtr[$m];
                    $eptr = $this->HeaderPtr[$m + 1];
                    break;
                } else if ($ip > $this->HeaderSip[$m - 1]) {
                    $sptr = $this->HeaderPtr[$m - 1];
                    $eptr = $this->HeaderPtr[$m];
                    break;
                }
                $h = $m - 1;
            } else {
                if ($m == $this->headerLen - 1) {
                    $sptr = $this->HeaderPtr[$m - 1];
                    $eptr = $this->HeaderPtr[$m];
                    break;
                } else if ($ip <= $this->HeaderSip[$m + 1]) {
                    $sptr = $this->HeaderPtr[$m];
                    $eptr = $this->HeaderPtr[$m + 1];
                    break;
                }
                $l = $m + 1;
            }
        }
        if ($sptr == 0) return NULL;
        $blockLen = $eptr - $sptr;
        fseek($this->dbFileHandler, $sptr);
        $index = fread($this->dbFileHandler, $blockLen + INDEX_BLOCK_LENGTH);
        $dataPtr = 0;
        $l = 0;
        $h = $blockLen / INDEX_BLOCK_LENGTH;
        while ($l <= $h) {
            $m = (($l + $h) >> 1);
            $p = (int)($m * INDEX_BLOCK_LENGTH);
            $sip = self::getLong($index, $p);
            if ($ip < $sip) {
                $h = $m - 1;
            } else {
                $eip = self::getLong($index, $p + 4);
                if ($ip > $eip) {
                    $l = $m + 1;
                } else {
                    $dataPtr = self::getLong($index, $p + 8);
                    break;
                }
            }
        }
        if ($dataPtr == 0) return NULL;
        $dataLen = (($dataPtr >> 24) & 0xFF);
        $dataPtr = ($dataPtr & 0x00FFFFFF);
        fseek($this->dbFileHandler, $dataPtr);
        $data = fread($this->dbFileHandler, $dataLen);
        /*return array(
            'city_id' => self::getLong($data, 0),
            'region' => substr($data, 4)
        );*/
        return substr($data, 4);
    }

    public static function safeIp2long($ip)
    {
        $ip = ip2long($ip);
        if ($ip < 0 && PHP_INT_SIZE == 4) {
            $ip = sprintf("%u", $ip);
        }

        return $ip;
    }

    public static function getLong($b, $offset)
    {
        $val = (
            (ord($b[$offset++])) |
            (ord($b[$offset++]) << 8) |
            (ord($b[$offset++]) << 16) |
            (ord($b[$offset]) << 24)
        );
        if ($val < 0 && PHP_INT_SIZE == 4) {
            $val = sprintf("%u", $val);
        }

        return $val;
    }

    public function __destruct()
    {
        if ($this->dbFileHandler != NULL) {
            fclose($this->dbFileHandler);
        }
        $this->dbBinStr = NULL;
        $this->HeaderSip = NULL;
        $this->HeaderPtr = NULL;
    }
}
