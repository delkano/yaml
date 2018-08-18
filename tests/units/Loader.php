<?php

namespace Dallgoot\Yaml\tests\units;

use atoum;

class Loader extends atoum
{
    private const EXAMPLES_FOLDER = __DIR__."/../cases/examples/";

    public function test__constructWithNothing()
    {
        // $this->newTestedInstance();
        $loaderMock = $this->newTestedInstance();
        $myClassReflection = new \ReflectionClass(get_class($loaderMock));
        $debug = $myClassReflection->getProperty('debug');
        $debug->setAccessible(true);
        $options = $myClassReflection->getProperty('options');
        $options->setAccessible(true);
        $defaultOptions = $this->testedInstance::EXCLUDE_DIRECTIVES &
                          $this->testedInstance::IGNORE_COMMENTS &
                          $this->testedInstance::NO_PARSING_EXCEPTIONS &
                          $this->testedInstance::NO_OBJECT_FOR_DATE;
        $this
            ->given($loaderMock)
            ->then
                ->integer($debug->getValue($loaderMock))->isIdenticalTo(0)
                ->integer($options->getValue($loaderMock))->isIdenticalTo($defaultOptions);
        ;
    }


    // public function loadDataProvider()
    // {
    //  return new RecursiveDirectoryIterator(EXAMPLES_FOLDER, FilesystemIterator::SKIP_DOTS);
    // }

    // public function testLoad(string $fileName)
    // {
    //  $this->
    // }



    // public function onSpecialType($value='')
    // {
    //  # code...
    // }

    // public function onDeepestType($value='')
    // {
    //  # code...
    // }
}