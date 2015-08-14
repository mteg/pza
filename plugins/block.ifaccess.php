<?
    function smarty_block_ifaccess($params, $content, &$S, &$repeat)
    {
        if(access::has($params["right"]) && $content)
            return $content;
    }
