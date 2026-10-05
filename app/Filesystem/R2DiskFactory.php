<?php

namespace App\Filesystem;

use Aws\CommandInterface;
use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter as LaravelS3Adapter;
use Illuminate\Support\Arr;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Filesystem;
use League\Flysystem\Visibility;

class R2DiskFactory
{
    public function make(array $config): LaravelS3Adapter
    {
        $clientConfig = [
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
        ];

        if (! empty($config['key']) && ! empty($config['secret'])) {
            $clientConfig['credentials'] = new Credentials($config['key'], $config['secret']);
        }
        if (isset($config['http_handler'])) {
            $clientConfig['http_handler'] = $config['http_handler'];
        }

        $client = new S3Client($clientConfig);
        $client->getHandlerList()->appendInit(function (callable $handler) {
            return function (CommandInterface $command, $request = null) use ($handler) {
                // R2 does not implement S3 object ACL headers. Bucket access remains private;
                // application access is enforced by the authorized Laravel download route.
                if (in_array($command->getName(), ['PutObject', 'CreateMultipartUpload', 'CopyObject'], true)) {
                    $command->offsetUnset('ACL');
                }

                return $handler($command, $request);
            };
        }, 'r2.omit-unsupported-acl');

        $adapter = new AwsS3V3Adapter(
            $client,
            $config['bucket'],
            (string) ($config['root'] ?? ''),
            new PortableVisibilityConverter(Visibility::PRIVATE),
            null,
            $config['options'] ?? [],
            $config['stream_reads'] ?? false,
        );
        $filesystemConfig = Arr::only($config, [
            'directory_visibility', 'disable_asserts', 'temporary_url', 'url', 'visibility',
        ]);
        $filesystemConfig['retain_visibility'] = false;

        return new LaravelS3Adapter(
            new Filesystem($adapter, $filesystemConfig),
            $adapter,
            $config,
            $client,
        );
    }
}
