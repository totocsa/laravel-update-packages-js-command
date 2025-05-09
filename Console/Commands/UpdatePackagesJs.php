<?php

namespace Totocsa\UpdatePackagesJsCommand\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use SplFileInfo;

class UpdatePackagesJs extends Command
{
    const ds = DIRECTORY_SEPARATOR;

    protected $signature = 'update:packagejs {--vendor=} {--since=} {--doit} {--show-newer} {--ice}';
    protected $description = 'Update the resources/js directory of packages based on the resources/js directory.';
    protected $fileSystem;
    protected $packagesFiles = [];
    protected $vendor;
    protected $vendorDir;
    protected $since;
    protected $doit;
    protected $showNewer;
    protected $ice;

    public function handle()
    {
        $this->doit = $this->option('doit');
        $this->showNewer = $this->option('show-newer');
        $this->ice = $this->option('ice');
        $this->vendor = $this->ice ? 'totocsa' : $this->option('vendor');
        $this->since = $this->ice ? config('ice.install_timestamp') : $this->option('since');
        $this->vendorDir = base_path($this::ds . 'vendor' . $this::ds . $this->vendor);

        if ($this->validateOptions()) {
            $this->fillPackagesFiles();

            if ($this->checkDuplicated()) {
                $this->update();
            }

            return Command::SUCCESS;
        } else {
            return Command::INVALID;
        }
    }

    protected function update()
    {
        $pathPrefix = resource_path('js');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'), \FilesystemIterator::SKIP_DOTS)
        );

        $newFiles = [];
        foreach ($iterator as $item) {
            if (date('Y.m.d H:i:s', $item->getMTime()) >= $this->since) {
                $absolutePath = $item->getPathname();
                $relativePath = substr($absolutePath, strlen($pathPrefix) + 1);

                foreach ($this->packagesFiles as $packageName => $files) {
                    $index = array_search($relativePath, array_column($files, 'relativePath'));
                    if ($index !== false) {
                        break;
                    }
                }

                if ($index === false) {
                    $newFiles[] = $relativePath;
                } else {
                    $itemMTime = $item->getMTime();
                    $indexMTime = (new SplFileInfo($this->packagesFiles[$packageName][$index]['absolutePath']))->getMTime();

                    if ($indexMTime >= $itemMTime) {
                        if ($this->showNewer) {
                            $this->line('There is a new file in the package.');
                            $this->line("{$this->packagesFiles[$packageName][$index]['absolutePath']}\n");
                        }
                    } else {
                        if ($this->doit) {
                            $this->line("Copy $absolutePath");
                            $this->line("To {$this->packagesFiles[$packageName][$index]['absolutePath']}\n");
                            copy($absolutePath, $this->packagesFiles[$packageName][$index]['absolutePath']);
                        } else {
                            $this->line("Newer: $absolutePath");
                            $this->line("Target: {$this->packagesFiles[$packageName][$index]['absolutePath']}\n");
                        }
                    }
                }
            }
        }

        if (count($newFiles) > 0) {
            $this->info("New files. Copy them into the appropriate package.");
            foreach ($newFiles as $v) {
                $this->info($v);
            }
        }
    }

    protected function checkDuplicated()
    {
        $relativeFiles = [];

        foreach ($this->packagesFiles as $packageName => $files) {
            foreach ($files as $v) {
                $relativeFiles[$v['relativePath']][] = $packageName;
            }
        }

        $allAtOnce = true;
        foreach ($relativeFiles as $file => $packages) {
            if (count($packages) > 1) {
                $allAtOnce = false;
                natsort($packages);
                $this->error("Error. The $file file is included in several packages.");
                $this->line('Packages: ' . implode(', ', $packages) . '.');
            }
        }

        return $allAtOnce;
    }

    protected function fillPackagesFiles()
    {
        $iterator1 = new \DirectoryIterator($this->vendorDir);

        foreach ($iterator1 as $item1) {
            if ($item1->isDir() && !$item1->isDot()) {
                $packageDir = $this->vendorDir . $this::ds . $item1->getFilename();

                $jsDir = $packageDir . $this::ds . 'resources' . $this::ds . 'js';

                if (is_dir($jsDir)) {
                    $lenJsDir = strlen($jsDir) + 1;
                    $this->packagesFiles[$item1->getFilename()] = [];

                    $iterator2 = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($jsDir, \FilesystemIterator::SKIP_DOTS)
                    );

                    foreach ($iterator2 as $item2) {
                        $this->packagesFiles[$item1->getFilename()][] = [
                            'absolutePath' => $item2->getPathname(),
                            'relativePath' => substr($item2->getPathname(), $lenJsDir),
                        ];
                    }
                }
            }
        }
    }

    protected function validateOptions()
    {
        $valid = true;

        $data = [
            'vendor' => $this->vendor,
            'since' => $this->since,
        ];

        $rules = [
            'vendor' => 'required',
            'since' => ['required', Rule::date()->format('Y.m.d H:i:s')]
        ];

        $validator = Validator::make($data, $rules, [
            'required' => '(vendor and since) or ice field is required.'
        ]);

        $messages = $validator->messages()->toArray();

        if (!is_dir($this->vendorDir)) {
            $messages['vendor'][] = "The {$this->vendorDir} directory does not exist.";
        }

        if (!$validator->passes()) {
            $valid = false;

            foreach ($messages as $field => $msgs) {
                $this->line($field);
                $this->error(implode(PHP_EOL, $msgs));
            }
        }

        return $valid;
    }
}
