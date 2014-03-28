<?
abstract class content
{
    protected $S;

    function __construct($S)
    {
        $this->S = $S;
    }

    function render_object($id, $path)
    {
        fail(500, "While rendering {$id}: rendering objects is not supported by engine " . get_called_class());
    }

    function render_category($id, $path)
    {
        fail(500, "While rendering {$id}: indexing categories is not supported by engine " . get_called_class());
    }
}
