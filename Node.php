<?php
namespace YamlLoader;
class NODETYPES{
    const DIRECTIVE  = 0;
    const DOC_START = 1;
    const DOC_END = 2;
    const DOCUMENT = 4;
    const COMMENT = 8;
    const EMPTY   = 16;
    const ROOT    = 32;
    // single line or have children
    const KEY = 42; 
    const ITEM = 52;

    const PARTIAL = 62; // have a multi line quoted  string OR json definition
    const LITTERAL = 72;
    const LITTERAL_FOLDED = 82;

    // const NULL    = 92;
    const STRING    = 102;
    const BOOLEAN = 112;
    const NUMBER  = 122;
    const TAG = 132;
    const JSON = 142;
    
    const QUOTED = 148;
    const REF_DEF = 152;
    const REF_CALL = 164;
    public static $NOTBUILDABLE = [self::DIRECTIVE,
                                    self::ROOT,
                                    self::DOC_START,
                                    self::DOC_END,
                                    self::COMMENT,
                                    self::EMPTY,
                                    self::TAG];
    public static $LITTERALS = [self::LITTERAL, self::LITTERAL_FOLDED];

    public static function getName($constant)
    {
        return array_flip((new \ReflectionClass(self::class))->getConstants())[$constant];
            }
}
use YamlLoader\NODETYPES as NT;
/**
* 
*/
class Node
{
    private $_parent = NULL;
    // private $_nodeTypes = [];

    public $indent   = -1;
    public $line     = NULL;
    // public $name     = NULL;
    public $type     = NULL;
    public $value    = NULL;

    function __construct($nodeString=null, $line=null)
    {
        // $this->_nodeTypes = ;
        $this->line = $line;
        if(is_null($nodeString)){
            $this->type = NT::ROOT;
        }else{
            $this->parse($nodeString);
        }
        // var_dump($this);
    }
    public function setParent(Node $node)
    {
        $this->_parent = $node;
    }

    public function getParent($indent=null):Node
    {   
        if (is_null($indent)) {
             return $this->_parent ?? $this; 
        }
        $cursor = $this;
        while ($cursor->indent >= $indent) {
            $cursor = $cursor->_parent;
        }
        return $cursor;
    }

    public function add(Node $child)
    {
        $child->setParent($this);
        $current = $this->value;
        if (is_null($current)) {
            $this->value = $child;
            return;
        }elseif ($current instanceof Node){
            if ($current->type === NT::EMPTY) {
                $this->value = $child;
                return;
            }else{
                $this->value = new \SplQueue();
                $this->value->enqueue($current);
                $this->value->enqueue($child);
            }
        }elseif ($current instanceof \SplQueue) {
            $this->value->enqueue($child);
        }
    }

    public function getDeepestNode():Node
    {
        $cursor = $this;
        while ($cursor->value instanceof Node) {
            $cursor = $cursor->value;
        }
        return $cursor;
    }
    /**
    *  CAUTION : the types assumed here are NOT FINAL : they CAN be adjusted according to parent
    */
    //TODO : handle reference definitions/calls and tags and complex mappings
    private function parse(String $nodeString):Node
    {
        //permissive to tabs but replacement before processing
        $nodeValue = preg_replace("/\t/m", " ", $nodeString);
        $this->indent = strspn($nodeValue , ' ');
        $nodeValue = ltrim($nodeValue);
        if ($nodeValue === '') {
            $this->type = NT::EMPTY;
            $this->indent = 0;
        }elseif (substr($nodeValue, 0, 3) === '...'){
            $this->type = NT::DOC_END;
        }elseif (preg_match('/^([^-][^:#{["\']*)\s*:[ \t]*(.*)?/', $nodeValue, $matches)) {
            $this->type = NT::KEY; 
            $this->name = trim($matches[1]);
            if(isset($matches[2]) && !empty(trim($matches[2]))) {
                $n = new Node(trim($matches[2]), $this->line);
            }else{
                $n = new Node();
                $n->type = NT::EMPTY;
            }
            $n->setParent($this);
            $this->value = $n;
        }else{//can be of another type according to VALUE
            list($this->type, $this->value) = $this->_define($nodeValue);
        }
        return $this;
    }

    private function _define($nodeValue)
    {
        $v = substr($nodeValue, 1);
        switch ($nodeValue[0]) {
            case '%': return [NT::DIRECTIVE, $v];
            case '#': return [NT::COMMENT, $v];
            case '!': return [NT::TAG, $v];// TODO: handle tags
            case "&": return [NT::REF_DEF, $v];//REFERENCE  //TODO
            case "*": return [NT::REF_CALL, $v];
            case '>': return [NT::LITTERAL_FOLDED, null];
            case '|': return [NT::LITTERAL, null];
            case '"':
            case "'":
                return $this->isProperlyQuoted($nodeValue) ? [NT::QUOTED, $nodeValue] : [NT::PARTIAL, $nodeValue];
            case "{":
            case "[":
                return $this->isValidJSON($nodeValue) ? [NT::JSON, $nodeValue] : [NT::PARTIAL, $nodeValue];
            case "-":
                if(substr($nodeValue, 0, 3) === '---') return [NT::DOC_START, substr($nodeValue, 3)];
                if (preg_match('/^-[ \t]*(.*)$/', $nodeValue, $matches)){
                    $n = new Node(trim($matches[1]), $this->line);
                    $n->setParent($this);
                    return [NT::ITEM, $n];
                }
            default:
                return [NT::STRING, $nodeValue];
        }
    }

    public function serialize():array
    {
        $name = property_exists($this, 'name') ? "($this->name)" : null;
        $out = ['node' => implode('|',[$this->line, $this->indent,NT::getName($this->type).$name])];
        $v = $this->value;
        if($v instanceof \SplQueue) {
            $out['value'] = var_export($v, true);
            // for ($v->rewind(); $v->valid(); $v->next()) {
            //     $out['value'][] = $v->current()->serialize();//array_map(function($c){return $c->serialize();}, $this->children);
            // }
        }elseif($v instanceof Node){
            $out['value'] = $v->serialize();
        }else{
            $out['node'] .= "|".$v;
        }
        return $out;
    }

    public function __debugInfo() {
        $out = ['line'=>$this->line,
                'indent'=>$this->indent,
                'type' => NT::getName($this->type),
                'value'=> $this->value];
        property_exists($this, 'name') ? $out['type'] .= "($this->name)" : null;
        return $out;
    }

    public function __sleep()
    {
        return ["value"];
    }

    public function isProperlyQuoted(string $candidate)
    {// check Node value to see if properly enclosed or formed
        $regex = "/(['".'"]).*?(?<![\\\\])\1$/ms';
        // var_dump($candidate);
        return preg_match($regex, $candidate);
    }

    public function isValidJSON(string $candidate)
    {// check Node value to see if properly enclosed or formed
        json_decode($candidate);
        return json_last_error() === JSON_ERROR_NONE; 
    }

    public function getPhpValue()
    {
        switch ($this->type) {
            case NT::LITTERAL:;
            case NT::LITTERAL_FOLDED:;
            // case NT::NULL: 
            case NT::EMPTY:return null;
            case NT::BOOLEAN: return bool($this->value);
            case NT::NUMBER: return intval($this->value);
            case NT::JSON: return json_encode($this->value);
            case NT::QUOTED:
            case NT::REF_DEF:
            case NT::REF_CALL:
            case NT::STRING: return strval($this->value);
            
            case NT::TAG:;
            case NT::DIRECTIVE:
            case NT::DOC_START:
            case NT::DOC_END:
            case NT::DOCUMENT:
            case NT::COMMENT:
            case NT::EMPTY:
            case NT::ROOT:
            case NT::KEY:; 
            case NT::ITEM:return $this->value->getPhpValue();
            case NT::PARTIAL:; // have a multi line quoted  string OR json definition
            default: throw new \Exception("Error can not get PHP type for ".$this->_nodeTypes[$this->type], 1);
        }
    }
}