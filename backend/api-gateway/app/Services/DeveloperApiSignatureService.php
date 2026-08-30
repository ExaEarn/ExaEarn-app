<?php

declare(strict_types=1);

namespace App\Services;

class DeveloperApiSignatureService
{
    public function canonicalQuery(string $query): string
    {
        if ($query === '') return '';
        $pairs = array_map(function(string $part): array {
            [$key,$value]=array_pad(explode('=',$part,2),2,'');
            return [urldecode($key),urldecode($value)];
        },explode('&',$query));
        usort($pairs,fn(array $left,array $right)=>[$left[0],$left[1]]<=>[$right[0],$right[1]]);
        return implode('&',array_map(fn(array $pair)=>rawurlencode($pair[0]).'='.rawurlencode($pair[1]),$pairs));
    }

    public function canonical(string $method,string $path,string $query,string $timestamp,string $nonce,string $body): string
    {
        return strtoupper($method)."\n".'/'.ltrim($path,'/')."\n".$this->canonicalQuery($query)."\n".$timestamp."\n".$nonce."\n".hash('sha256',$body);
    }

    public function sign(string $secret,string $method,string $path,string $query,string $timestamp,string $nonce,string $body): string
    {
        return hash_hmac('sha256',$this->canonical($method,$path,$query,$timestamp,$nonce,$body),$secret);
    }
}
