<?php

class CryptedVideoStream // fol
{
    private $path = "";
    private $stream = "";
    private $start  = -1;
    private $end    = -1;
    private $size   = 0;
 
    function __construct($filePath) 
    {
        $this->path = $filePath;
    }

    /**
     * Decrypts a msg using sodium
     */
    private function decrypt($msg) {
        $key = "v6jM4s0hYqa52m8Bt5c1z7Celv6hFbnF";
        $nonce = "mf5cOb6g19QRbX93bD6Fpj5d";
        return sodium_crypto_secretbox_open($msg, $nonce, $key); // decr
    }

    /**
     * Open stream
     */
    private function open()
    {
        if (!($this->stream = fopen($this->path, 'rb'))) {
            die('Could not open stream for reading');
        }  
    }

    /**
     * Close curretly opened stream
     */
    private function end()
    {
        fclose($this->stream);
        exit;
    }

    /**
     * Calculate the size of the decrypted video, based on $val, 
     * the size of the encrypted video.
     * Works as long as the video has been encrypted by chunks of 1024 bytes
     * (each chunck takes 16 bytes more when encrypted)
     */
    private function clearSize($val)
    {
        $fullChuncksNb = floor($val/1040); // nb of chuncks of 1024 bytes (1024+16)
        $lastChunckSize = $val - ($fullChuncksNb * 1040) - 16; // size of the last chunk (decrypted)
        return $fullChuncksNb * 1024 + $lastChunckSize;
    }
    
    /**
     * Set proper header to serve the video content
     */
    private function setHeader()
    {
        ob_get_clean();
        
        header("Content-Type: video/mp4");
        header("Cache-Control: max-age=2592000, public");
        header("Expires: ".gmdate('D, d M Y H:i:s', time()+2592000) . ' GMT');
        header("Last-Modified: ".gmdate('D, d M Y H:i:s', @filemtime($this->path)) . ' GMT' );
        $this->start = 0;
        $this->size  = $this->clearSize(filesize($this->path)); 
        $this->cryptSize = filesize($this->path);
        $this->end   = $this->size - 1;
        header("Accept-Ranges: bytes"); 
        
        if (isset($_SERVER['HTTP_RANGE'])) {
  
            $c_start = $this->start;
            $c_end = $this->end;

            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $this->start-$this->end/$this->size");
                exit;
            }
            if ($range == '-') {
                $c_start = $this->size - substr($range, 1);
            }else{
                $range = explode('-', $range);
                $c_start = $range[0];
                $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $c_end;
            }
            $c_end = ($c_end > $this->end) ? $this->end : $c_end;
            if ($c_start > $c_end || $c_start > $this->size - 1 || $c_end >= $this->size) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $this->start-$this->end/$this->size");
                exit;
            }
            $this->start = $c_start;
            $this->end = $c_end;
            $this->length = $this->end - $this->start + 1;

            header('HTTP/1.1 206 Partial Content');
            header("Content-Length: " . $this->length); 
            header("Content-Range: bytes $this->start-$this->end/".$this->size);
        }
        else
        {
            header("Content-Length: ". $this->size);
        }  
    }

    /**
     * Perform the streaming of calculated range
     */
    private function stream()
    {
        set_time_limit(0);

        $bytesToRead = 1040;

        // z_start = index of the chunck containing start
        $z_start = $this->start;        
        $z_start /= 1024;               
        $z_start = floor($z_start);    

        // z_end = index of the chunck containing end 
        $z_end = $this->end;            
        $z_end /= 1024;                 
        $z_end = floor($z_end);  

        // let's go at the beginning of the chunk containing z_star
        fseek($this->stream, $z_start * $bytesToRead); 

        // read the chunk and decrypt it
        $data = fread($this->stream, $bytesToRead); // size 1040 
        $data = $this->decrypt($data);              // size 1024

        // extracts the relevant bytes from this chunk
        $len = ($z_end > $z_start) ? 1023 - ($this->start%1024) + 1 : $this->end - $this->start + 1;
        $bytes = mb_substr($data, ($this->start%1024), $len, '8bit');

        // keep record of how much bytes has been returned so far 
        $written = $len;

        echo $bytes; 
        flush();

        // i = pos of the cursor in the crypted stream (could be 1040, 2080, ...)
        $i = ftell($this->stream);

        while (!feof($this->stream) && $i < $this->cryptSize)
        {
            // if we are in last chunk of the crypted file (length < 1024)
            if (($i + $bytesToRead) > $this->cryptSize - 1) 
                $bytesToRead = $this->cryptSize - $i;

            // read and decrypt
            $data = fread($this->stream, $bytesToRead);
            $data = $this->decrypt($data);

            // if I don't need all the bytes of this chunck
            if ($written + 1024 > $this->length) {
                $len = $this->length - $written; 
                $bytes2 = mb_substr($data, 0, $len, '8bit');
                echo $bytes2;
                flush(); 
                return;
            } else {
                echo $data;
                $written += 1024;
                flush(); 
            }

            $i += 1040;
        }
    }
     
    /**
     * Start streaming video content
     */
    function start()
    {
        $this->open();
        $this->setHeader();
        $this->stream();
        $this->end();
    }
}

$filePath = "file";
$stream = new CryptedVideoStream($filePath); 
$stream->start();

?>
