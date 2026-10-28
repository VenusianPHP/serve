<?php

namespace Mariner\View;

use BackedEnum;
use Voyager\Vessel\ControlPanel;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\Str;
use Mariner\View\Compilers\ComponentTagCompiler;

use function Voyager\NutsAndBolts\enum_value;

class DynamicComponent extends Component
{
    /**
     * The name of the component.
     *
     * @var string
     */
    public $component;

    /**
     * The component tag compiler instance.
     *
     * @var \Mariner\View\Compilers\ComponentTagCompiler
     */
    protected static $compiler;

    /**
     * The cached component classes.
     *
     * @var array
     */
    protected static $componentClasses = [];

    /**
     * Create a new component instance.
     *
     * @param  \BackedEnum|string  $component
     */
    public function __construct(BackedEnum|string $component)
    {
        $this->component = (string) enum_value($component);
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Closure
     */
    public function render()
    {
        $template = <<<'EOF'
<?php extract((new \Voyager\NutsAndBolts\Collection($attributes->getAttributes()))->mapWithKeys(function ($value, $key) { return [Voyager\NutsAndBolts\Str::camel(str_replace([':', '.'], ' ', $key)) => $value]; })->all(), EXTR_SKIP); ?>
{{ props }}
<x-{{ component }} {{ bindings }} {{ attributes }}>
{{ slots }}
{{ defaultSlot }}
</x-{{ component }}>
EOF;

        return function ($data) use ($template) {
            $bindings = $this->bindings($class = $this->classForComponent());

            return str_replace(
                [
                    '{{ component }}',
                    '{{ props }}',
                    '{{ bindings }}',
                    '{{ attributes }}',
                    '{{ slots }}',
                    '{{ defaultSlot }}',
                ],
                [
                    $this->component,
                    $this->compileProps($bindings),
                    $this->compileBindings($bindings),
                    class_exists($class) ? '{{ $attributes }}' : '',
                    $this->compileSlots($data['__mariner_slots']),
                    '{{ $slot ?? "" }}',
                ],
                $template
            );
        };
    }

    /**
     * Compile the @props directive for the component.
     *
     * @param  array  $bindings
     * @return string
     */
    protected function compileProps(array $bindings)
    {
        if (empty($bindings)) {
            return '';
        }

        return '@props('.'[\''.implode('\',\'', (new Collection($bindings))->map(function ($dataKey) {
            return Str::camel($dataKey);
        })->all()).'\']'.')';
    }

    /**
     * Compile the bindings for the component.
     *
     * @param  array  $bindings
     * @return string
     */
    protected function compileBindings(array $bindings)
    {
        return (new Collection($bindings))
            ->map(fn ($key) => ':'.$key.'="$'.Str::camel(str_replace([':', '.'], ' ', $key)).'"')
            ->implode(' ');
    }

    /**
     * Compile the slots for the component.
     *
     * @param  array  $slots
     * @return string
     */
    protected function compileSlots(array $slots)
    {
        return (new Collection($slots))
            ->reject(fn ($slot, $name) => $name === '__default')
            ->map(fn ($slot, $name) => '<x-slot name="'.$name.'" :attributes="$'.$name.'->attributes">{{ $'.$name.' }}</x-slot>')
            ->implode(PHP_EOL);
    }

    /**
     * Get the class for the current component.
     *
     * @return string
     */
    protected function classForComponent()
    {
        return static::$componentClasses[$this->component] ?? static::$componentClasses[$this->component] =
                    $this->compiler()->componentClass($this->component);
    }

    /**
     * Get the names of the variables that should be bound to the component.
     *
     * @param  string  $class
     * @return array
     */
    protected function bindings(string $class)
    {
        [$data] = $this->compiler()->partitionDataAndAttributes($class, $this->attributes->getAttributes());

        return array_keys($data->all());
    }

    /**
     * Get an instance of the Blade tag compiler.
     *
     * @return \Mariner\View\Compilers\ComponentTagCompiler
     */
    protected function compiler()
    {
        if (! static::$compiler) {
            static::$compiler = new ComponentTagCompiler(
                ControlPanel::getInstance()->make('blade.compiler')->getClassComponentAliases(),
                ControlPanel::getInstance()->make('blade.compiler')->getClassComponentNamespaces(),
                ControlPanel::getInstance()->make('blade.compiler')
            );
        }

        return static::$compiler;
    }
}
