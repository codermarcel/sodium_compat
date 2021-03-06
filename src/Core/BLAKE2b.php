<?php

/**
 * Class ParagonIE_Sodium_Core_BLAKE2b
 *
 * Based on the work of Devi Mandiri in devi/salt.
 */
abstract class ParagonIE_Sodium_Core_BLAKE2b extends ParagonIE_Sodium_Core_Util
{
    /**
     * @var SplFixedArray
     */
    protected static $iv;

    /**
     * @var int[][]
     */
    protected static $sigma = array(
        array(  0,  1,  2,  3,  4,  5,  6,  7,  8,  9, 10, 11, 12, 13, 14, 15),
        array( 14, 10,  4,  8,  9, 15, 13,  6,  1, 12,  0,  2, 11,  7,  5,  3),
        array( 11,  8, 12,  0,  5,  2, 15, 13, 10, 14,  3,  6,  7,  1,  9,  4),
        array(  7,  9,  3,  1, 13, 12, 11, 14,  2,  6,  5, 10,  4,  0, 15,  8),
        array(  9,  0,  5,  7,  2,  4, 10, 15, 14,  1, 11, 12,  6,  8,  3, 13),
        array(  2, 12,  6, 10,  0, 11,  8,  3,  4, 13,  7,  5, 15, 14,  1,  9),
        array( 12,  5,  1, 15, 14, 13,  4, 10,  0,  7,  6,  3,  9,  2,  8, 11),
        array( 13, 11,  7, 14, 12,  1,  3,  9,  5,  0, 15,  4,  8,  6,  2, 10),
        array(  6, 15, 14,  9, 11,  3,  0,  8, 12,  2, 13,  7,  1,  4, 10,  5),
        array( 10,  2,  8,  4,  7,  6,  1,  5, 15, 11,  9, 14,  3, 12, 13 , 0),
        array(  0,  1,  2,  3,  4,  5,  6,  7,  8,  9, 10, 11, 12, 13, 14, 15),
        array( 14, 10,  4,  8,  9, 15, 13,  6,  1, 12,  0,  2, 11,  7,  5,  3)
    );

    const BLOCKBYTES = 128;
    const OUTBYTES   = 64;
    const KEYBYTES   = 64;

    /**
     * Turn two 32-bit integers into a fixed array representing a 64-bit integer.
     *
     * @param int $high
     * @param int $low
     * @return SplFixedArray
     */
    protected static function new64($high, $low)
    {
        $i64 = new SplFixedArray(2);
        $i64[0] = $high & 0xffffffff;
        $i64[1] = $low & 0xffffffff;
        return $i64;
    }

    /**
     * Convert an arbitrary number into an SplFixedArray of two 32-bit integers
     * that represents a 64-bit integer.
     *
     * @param int $num
     * @return SplFixedArray
     */
    protected static function to64($num)
    {
        $hi = 0; $lo = $num & 0xffffffff;

        if ((+(abs($num))) >= 1) {
            if ($num > 0) {
                $hi = min((+(floor($num/4294967296))), 4294967295);
            } else {
                $hi = ~~((+(ceil(($num - (+((~~($num)))))/4294967296))));
            }
        }

        return self::new64($hi, $lo);
    }

    /**
     * Adds two 64-bit integers together, returning their sum as a SplFixedArray
     * containing two 32-bit integers (representing a 64-bit integer).
     *
     * @param SplFixedArray $x
     * @param SplFixedArray $y
     * @return SplFixedArray
     */
    protected static function add64($x, $y)
    {
        $l = ($x[1] + $y[1]) & 0xffffffff;
        return self::new64($x[0] + $y[0] + (($l < $x[1]) ? 1 : 0), $l);
    }

    /**
     * @param SplFixedArray $x
     * @param SplFixedArray $y
     * @param SplFixedArray $z
     * @return SplFixedArray
     */
    protected static function add364($x, $y, $z)
    {
        return self::add64($x, self::add64($y, $z));
    }

    /**
     * @param SplFixedArray $x
     * @param SplFixedArray $y
     * @return SplFixedArray
     * @throws Exception
     */
    protected static function xor64(SplFixedArray $x, SplFixedArray $y)
    {
        if (!is_numeric($x[0])) {
            throw new Exception('x[0] is not an integer');
        }
        if (!is_numeric($x[1])) {
            throw new Exception('x[1] is not an integer');
        }
        if (!is_numeric($y[0])) {
            throw new Exception('y[0] is not an integer');
        }
        if (!is_numeric($y[1])) {
            throw new Exception('y[1] is not an integer');
        }
        return self::new64($x[0] ^ $y[0], $x[1] ^ $y[1]);
    }

    /**
     * @param SplFixedArray $x
     * @param int $c
     * @return SplFixedArray
     */
    protected static function rotr64($x, $c)
    {
        $l0 = 0;
        $c = 64 - $c;

        if ($c < 32) {
            $h0 = ($x[0] << $c) | (($x[1] & ((1 << $c) - 1) << (32 - $c)) >> (32 - $c));
            $l0 = $x[1] << $c;
        } else {
            $h0 = $x[1] << ($c - 32);
        }

        $h1 = 0;
        $c1 = 64 - $c;

        if ($c1 < 32) {
            $h1 = $x[0] >> $c1;
            $l1 = ($x[1] >> $c1) | ($x[0] & ((1 << $c1) - 1)) << (32 - $c1);
        } else {
            $l1 = $x[0] >> ($c1 - 32);
        }

        return self::new64($h0 | $h1, $l0 | $l1);
    }

    /**
     * @param SplFixedArray $x
     * @return int
     */
    protected static function flatten64($x)
    {
        return ($x[0] * 4294967296 + $x[1]);
    }

    /**
     * @param SplFixedArray $x
     * @param int $i
     * @return SplFixedArray
     */
    protected static function load64(SplFixedArray $x, $i)
    {
        $l = $x[$i]   | ($x[$i+1]<<8) | ($x[$i+2]<<16) | ($x[$i+3]<<24);
        $h = $x[$i+4] | ($x[$i+5]<<8) | ($x[$i+6]<<16) | ($x[$i+7]<<24);
        return self::new64($h, $l);
    }

    /**
     * @param SplFixedArray $x
     * @param int $i
     * @param SplFixedArray $u
     * @return void
     */
    protected static function store64(SplFixedArray $x, $i, SplFixedArray $u)
    {
        $x[$i]   = ($u[1] & 0xff); $u[1] >>= 8;
        $x[$i+1] = ($u[1] & 0xff); $u[1] >>= 8;
        $x[$i+2] = ($u[1] & 0xff); $u[1] >>= 8;
        $x[$i+3] = ($u[1] & 0xff);
        $x[$i+4] = ($u[0] & 0xff); $u[0] >>= 8;
        $x[$i+5] = ($u[0] & 0xff); $u[0] >>= 8;
        $x[$i+6] = ($u[0] & 0xff); $u[0] >>= 8;
        $x[$i+7] = ($u[0] & 0xff);
    }

    /**
     * This just sets the $iv static variable.
     * @return void
     */
    public static function pseudoConstructor()
    {
        static $called = false;
        if ($called) {
            return;
        }
        self::$iv = new SplFixedArray(8);
        self::$iv[0] = self::new64(0x6a09e667, 0xf3bcc908);
        self::$iv[1] = self::new64(0xbb67ae85, 0x84caa73b);
        self::$iv[2] = self::new64(0x3c6ef372, 0xfe94f82b);
        self::$iv[3] = self::new64(0xa54ff53a, 0x5f1d36f1);
        self::$iv[4] = self::new64(0x510e527f, 0xade682d1);
        self::$iv[5] = self::new64(0x9b05688c, 0x2b3e6c1f);
        self::$iv[6] = self::new64(0x1f83d9ab, 0xfb41bd6b);
        self::$iv[7] = self::new64(0x5be0cd19, 0x137e2179);

        $called = true;
    }

    /**
     * Returns a fresh BLAKE2 context.
     *
     * @return SplFixedArray
     */
    protected static function context()
    {
        $ctx    = new SplFixedArray(5);
        $ctx[0] = new SplFixedArray(8);   // h
        $ctx[1] = new SplFixedArray(2);   // t 
        $ctx[2] = new SplFixedArray(2);   // f
        $ctx[3] = new SplFixedArray(256); // buf
        $ctx[4] = 0;                      // buflen

        for ($i = 8; $i--;) {
            $ctx[0][$i] = self::$iv[$i];
        }
        for ($i = 256; $i--;) {
            $ctx[3][$i] = 0;
        }

        $zero = self::new64(0, 0);
        $ctx[1][0] = $zero;
        $ctx[1][1] = $zero;
        $ctx[2][0] = $zero;
        $ctx[2][1] = $zero;

        return $ctx;
    }

    /**
     * @param SplFixedArray $ctx
     * @param SplFixedArray $buf
     * @return void
     */
    protected static function compress(SplFixedArray $ctx, SplFixedArray $buf)
    {
        $m = new SplFixedArray(16);
        $v = new SplFixedArray(16);

        for ($i = 16; $i--;) {
            $m[$i] = self::load64($buf, $i*8);
        }

        for ($i = 8; $i--;) {
            $v[$i] = $ctx[0][$i];
        }

        $v[ 8] = self::$iv[0];
        $v[ 9] = self::$iv[1];
        $v[10] = self::$iv[2];
        $v[11] = self::$iv[3];

        $v[12] = self::xor64($ctx[1][0], self::$iv[4]);
        $v[13] = self::xor64($ctx[1][1], self::$iv[5]);
        $v[14] = self::xor64($ctx[2][0], self::$iv[6]);
        $v[15] = self::xor64($ctx[2][1], self::$iv[7]);

        for ($r = 0; $r < 12; ++$r) {
            $v = self::G($r, 0,  0,  4,  8, 12, $v, $m);
            $v = self::G($r, 1,  1,  5,  9, 13, $v, $m);
            $v = self::G($r, 2,  2,  6, 10, 14, $v, $m);
            $v = self::G($r, 3,  3,  7, 11, 15, $v, $m);
            $v = self::G($r, 4,  0,  5, 10, 15, $v, $m);
            $v = self::G($r, 5,  1,  6, 11, 12, $v, $m);
            $v = self::G($r, 6,  2,  7,  8, 13, $v, $m);
            $v = self::G($r, 7,  3,  4,  9, 14, $v, $m);
        }

        for ($i = 8; $i--;) {
            $ctx[0][$i] = self::xor64(
                $ctx[0][$i], self::xor64($v[$i], $v[$i+8])
            );
        }
    }

    /**
     * @param int $r
     * @param int $i
     * @param int $a
     * @param int $b
     * @param int $c
     * @param int $d
     * @param SplFixedArray $v
     * @param SplFixedArray $m
     * @return SplFixedArray
     */
    public static function G($r, $i, $a, $b, $c, $d, SplFixedArray $v, SplFixedArray $m)
    {
        $v[$a] = self::add364($v[$a], $v[$b], $m[self::$sigma[$r][2*$i]]);
        $v[$d] = self::rotr64(self::xor64($v[$d], $v[$a]), 32);
        $v[$c] = self::add64($v[$c], $v[$d]);
        $v[$b] = self::rotr64(self::xor64($v[$b], $v[$c]), 24);
        $v[$a] = self::add364($v[$a], $v[$b], $m[self::$sigma[$r][2*$i+1]]);
        $v[$d] = self::rotr64(self::xor64($v[$d], $v[$a]), 16);
        $v[$c] = self::add64($v[$c], $v[$d]);
        $v[$b] = self::rotr64(self::xor64($v[$b], $v[$c]), 63);
        return $v;
    }

    /**
     * @param SplFixedArray $ctx
     * @param int $inc
     * @return void
     */
    protected static function increment_counter($ctx, $inc)
    {
        $t = self::to64($inc);
        $ctx[1][0] = self::add64($ctx[1][0], $t);
        if (self::flatten64($ctx[1][0]) < $inc) {
            $ctx[1][1] = self::add64($ctx[1][1], self::to64(1));
        }
    }

    /**
     * @param SplFixedArray $ctx
     * @param SplFixedArray $p
     * @param int $plen
     * @return void
     */
    public static function update(SplFixedArray $ctx, $p, $plen)
    {
        $offset = 0;
        while ($plen > 0) {
            $left = $ctx[4];
            $fill = 256 - $left;

            if ($plen > $fill) {
                for ($i = $fill; $i--;) {
                    $ctx[3][$i+$left] = $p[$i+$offset];
                }

                $ctx[4] += $fill;

                self::increment_counter($ctx, 128);
                self::compress($ctx, $ctx[3]);

                for ($i = 128; $i--;) {
                    $ctx[3][$i] = $ctx[3][$i+128];
                }

                $ctx[4] -= 128;
                $offset += $fill;
                $plen -= $fill;
            } else {
                for ($i = $plen; $i--;) {
                    $ctx[3][$i+$left] = $p[$i+$offset];
                }
                $ctx[4] += $plen;
                $offset += $plen;
                $plen -= $plen;
            }
        }
    }

    /**
     * @param SplFixedArray $ctx
     * @param SplFixedArray $out
     * @return SplFixedArray
     */
    public static function finish(SplFixedArray $ctx, SplFixedArray $out)
    {
        if ($ctx[4] > 128) {
            self::increment_counter($ctx, 128);
            self::compress($ctx, $ctx[3]);
            $ctx[4] -= 128;
            for ($i = $ctx[4]; $i--;) {
                $ctx[3][$i] = $ctx[3][$i+128];
            }
        }

        self::increment_counter($ctx, $ctx[4]);
        $ctx[2][0] = self::new64(0xffffffff, 0xffffffff);

        for ($i = 256 - $ctx[4]; $i--;) {
            $ctx[3][$i+$ctx[4]] = 0;
        }

        self::compress($ctx, $ctx[3]);

        $i = (int) (($out->getSize() - 1) / 8);
        for (; $i >= 0; --$i) {
            self::store64($out, $i * 8, $ctx[0][$i]);
        }
        return $out;
    }

    /**
     * @param SplFixedArray|null $key
     * @param int $outlen
     * @return SplFixedArray
     * @throws Exception
     */
    public static function init($key = null, $outlen = 64)
    {
        $klen = 0;

        if ($key !== null) {
            if (count($key) > 64) {
                throw new Exception('Invalid key size');
            }
            $klen = count($key);
        }

        if ($outlen > 64) {
            throw new Exception('Invalid output size');
        }

        $ctx = self::context();

        $p = new SplFixedArray(64);
        for ($i = 64; --$i;) $p[$i] = 0;

        $p[0] = $outlen; // digest_length
        $p[1] = $klen;   // key_length
        $p[2] = 1;       // fanout
        $p[3] = 1;       // depth

        $ctx[0][0] = self::xor64(
            $ctx[0][0],
            self::load64($p, 0)
        );

        if ($klen > 0 && $key instanceof SplFixedArray) {
            $block = new SplFixedArray(128);
            for ($i = 128; $i--;) {
                $block[$i] = 0;
            }
            for ($i = $klen; $i--;) {
                $block[$i] = $key[$i];
            }
            self::update($ctx, $block, 128);
        }

        return $ctx;
    }

    /**
     * Convert a string into an SplFixedArray of integers
     *
     * @param string $str
     * @return SplFixedArray
     */
    public static function stringToSplFixedArray($str = '')
    {
        $values = unpack('C*', $str);
        return SplFixedArray::fromArray(array_values($values));
    }

    /**
     * Convert an SplFixedArray of integers into a string
     *
     * @param SplFixedArray $a
     * @return string
     */
    public static function SplFixedArrayToString(SplFixedArray $a)
    {
        $arr = $a->toArray();
        $c = $a->count();
        array_unshift($arr, str_repeat('C', $c));
        return call_user_func_array('pack', $arr);
    }

    /**
     * @param SplFixedArray[SplFixedArray] $ctx
     * @return string
     */
    public static function contextToString(SplFixedArray $ctx)
    {
        $str = '';
        $ctxA = $ctx[0]->toArray();
        for ($i = 0; $i < 8; ++$i) {
            $str .= self::store32_le($ctxA[$i][1]);
            $str .= self::store32_le($ctxA[$i][0]);
        }
        for ($i = 0; $i < 2; ++$i) {
            $ctxA = $ctx[$i + 1]->toArray();
            $str .= self::store32_le($ctxA[0][1]);
            $str .= self::store32_le($ctxA[0][0]);
            $str .= self::store32_le($ctxA[1][1]);
            $str .= self::store32_le($ctxA[1][0]);
        }
        $str .= self::SplFixedArrayToString($ctx[3]);
        $str .= implode('', array(
            self::intToChr($ctx[4] & 0xff),
            self::intToChr(($ctx[4] << 8) & 0xff),
            self::intToChr(($ctx[4] << 16) & 0xff),
            self::intToChr(($ctx[4] << 24) & 0xff),
            self::intToChr(($ctx[4] << 32) & 0xff),
            self::intToChr(($ctx[4] << 40) & 0xff),
            self::intToChr(($ctx[4] << 48) & 0xff),
            self::intToChr(($ctx[4] << 56) & 0xff)
        ));
        return $str . "\x00";
    }

    /**
     * Creates an SplFixedArray containing other SplFixedArray elements, from
     * a string (compatible with \Sodium\crypto_generichash_{init, update, final})
     *
     * @param string $string
     * @return SplFixedArray
     */
    public static function stringToContext($string)
    {
        $ctx = self::context();
        for ($i = 0; $i < 8; ++$i) {
            $ctx[0][$i] = SplFixedArray::fromArray(
                array(
                    self::load_4(
                        self::substr($string, (8 * $i + 4), 4)
                    ),
                    self::load_4(
                        self::substr($string, (8 * $i + 0), 4)
                    )
                )
            );
        }
        for ($i = 1; $i <= 2; ++$i) {
            $ctx[$i][0] = SplFixedArray::fromArray(
                array(
                    self::load_4(self::substr($string, 64 + (8 * $i), 4)),
                    self::load_4(self::substr($string, 60 + (8 * $i), 4))
                )
            );
        }
        $ctx[3] = self::stringToSplFixedArray(self::substr($string, 96, 256));
        $int = 0;
        for ($i = 0; $i < 8; ++$i) {
            $int |= self::chrToInt($string[352 + $i]) << (8 * $i);
        }
        $ctx[4] = $int;
        return $ctx;
    }
}
