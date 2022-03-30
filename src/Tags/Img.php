<?php

namespace Schwartzmj\Img\Tags;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Statamic\Tags\Tags;
use Statamic\Facades\Asset;

class Img extends Tags
{
    private \Statamic\Assets\Asset $asset;
    private Collection $breakpointData;

    private String $sizes;
    private String $webpSources;
    private String $originalSources;
    private string $defaultImgSrc;

    private $breakpoints = [
        'sm' => 640,
        'md' => 768,
        'lg' => 1024,
        'xl' => 1280,
        '2xl' => 1536,
        'xs' => 320,
    ];

    /**a
     * The {{ img }} tag.
     *
     * @return string|array
     */
    public function index()
    {
        $otherParams = $this->params->except(['src', 'sizes', 'priority']);
        $this->priority = $this->params->has('priority');
        ['src' => $srcParam] = $this->validateParams(); // Validate params. Get what we need from them.
        $this->asset = $this->isAssetOrGetAsset($srcParam); // If 'src' param is an asset, assign it to $this->asset. Else, try and get the asset from the 'src' param (by URL). Throws error if not found.

        // TODO: do we want to check for a valid image asset here? (jpeg, png, etc). Should be an "isImage" method I believe.
        $this->alt = $this->params->get('alt') ?? $this->asset->get('alt', ''); // Assign 'alt'. Don't want to leave empty.

        // if SVG or GIF, we do not optimize.
        if($this->asset->extension == 'svg' || $this->asset->extension == 'gif') {
            return view('smj::img', [
                'asset' => $this->asset->set('alt', $this->alt),
                'priority' => $this->priority,
                'rest' => $otherParams,
                'noOptimize' => true,
            ]);
        }

        $sizesInput = $this->params->get('sizes') ?? '100'; // Get 'sizes'. Default to 100.
        $parsedSizesInput = $this->parseSizesInput($sizesInput); // Parse 'sizes' input.
        $this->breakpointData = $this->generateBreakpointData($parsedSizesInput); // Generate breakpoint data for each breakpoint with given input.

        $this->sizes = $this->generateSizes();
        $this->webpSources = $this->generateSources(webp:true);
        $this->originalSources = $this->generateSources(webp:false);
        $this->defaultImgSrc = \Statamic::tag('glide')->params([ 'src' => $this->asset, 'width' => '768', 'fit' => 'max', 'quality' => '70' ]);
        // TODO: handle 'priority' param. Default to lazy.
        // TODO: when we grab params at the start, grab any other given params and return those (like 'class' or misc ones like 'x-data')
        // $additionalProps = $otherParams->map(function ($value, $key) {
        //     return ($value);
        // });
        // dd($additionalProps);
        $viewData = [
            'sizes' => $this->sizes,
            'webpSources' => $this->webpSources,
            'originalSources' => $this->originalSources,
            'defaultImgSrc' => $this->defaultImgSrc,
            'asset' => $this->asset->set('alt', $this->alt),
            'priority' => $this->priority,
            'rest' => $otherParams,
        ];
        // dd($viewData);
        return view('smj::img', $viewData);
    }

    private function parseSizesInput($input) {
        // ->filter() removes empty values in the array (spaces from any line breaks / whitespace in templating)
        // then we ->map and trim white space from each string (e.g. '\n\ or other spaces)
        $colonSeparatedBreakpointLabelValuePairs = collect(explode(' ', $input))->filter()->map(function ($item) {
            return trim($item);
        }); // e.g. '100 md:50 xl:33.33' into ['100', 'md:50', 'xl:33.33']

        // Generate array of label/screen width pairs from given input. e.g.:
        // [ 'xs' => 100', md' => 50, 'xl' => 33] (from e.g. input of: "100 md:50 xl:33.33")
        $breakpointLabelAndScreenWidthPairs = $colonSeparatedBreakpointLabelValuePairs->flatMap(function ($item) {
            $bpLabelAndScreenWidthValue = explode(':', $item);
            // If there was no breakpoint given  (e.g. md: or xl:) (count of the array is 1), then it is the 'base' breakpoint, so we assign it a key of 'xs'

            [$breakpointLabel, $screenWidthValue] = match (count($bpLabelAndScreenWidthValue)) {
                1 => ['xs', $bpLabelAndScreenWidthValue[0]],
                2 => [$bpLabelAndScreenWidthValue[0], $bpLabelAndScreenWidthValue[1]],
                default => throw 'You probably entered too many colons (:) in one of your image sizes.'
            };
            if (!is_numeric($screenWidthValue)) {
                throw new \Exception('A given screen width value was not numeric. ' .  $screenWidthValue);
            }
            return [$breakpointLabel => intVal(ceil(floatVal($screenWidthValue)))];
        });

        return $breakpointLabelAndScreenWidthPairs;
    }

    private function generateBreakpointData($parsedSizesInput) { // param = e.g. ['xs' => 100, 'md' => 50, 'xl' => 33]
        // goal is to return data for each breakpoint. e.g.:
        // [ 'pxScreenWidth' => 768,
        //     'percentOfScreenImageWidth' => 50,
        //     'calculatedPxImageWidth' => 384, // calculated by: 768 * (50/100)
        //     'sizesStr' => "(min-width: 768px) 50vw,",
        //     'breakpointLabel' => 'md' ]

        // set initial (lowest breakpoint) screen width.
        $currentPercentImageWidth = $parsedSizesInput['xs']; // 'xs' always exists from parsing the input before this function is called.

        $eachBreakpointData = collect([]);

        // for each breakpoint, check if the given sizesInput has a value for that breakpoint. If so, use that value. If not, use the previous breakpoint's value.
        foreach($this->breakpoints as $bp => $val) {
            if ($parsedSizesInput->has($bp)) {
                $currentPercentImageWidth = $parsedSizesInput->get($bp); // set current breakpoint value to use for next breakpoint if it is not set.
                $calculatedPxImageWidth = intVal(floor($this->breakpoints[$bp] * ($currentPercentImageWidth / 100))); // calculate image width needed for this breakpoint.
            } else {
                $calculatedPxImageWidth = intVal(floor($this->breakpoints[$bp] * ($currentPercentImageWidth / 100)));
            }
            $eachBreakpointData->add(collect([
                'pxScreenWidth' => $val,
                'percentOfScreenImageWidth' => $currentPercentImageWidth,
                'calculatedPxImageWidth' => $calculatedPxImageWidth,
                'sizesStr' => $bp === 'xs' ? "{$currentPercentImageWidth}vw" : "(min-width: {$val}px) {$currentPercentImageWidth}vw,",
                'breakpointLabel' => $bp
            ]));
        }
        return $eachBreakpointData;
    }

    private function generateSizes() {
        $sizesStr = '';
        foreach($this->breakpointData as $breakpointData) {
            $sizesStr .= $breakpointData->get('sizesStr');
        }
        return $sizesStr;
    }

    private function generateSources($webp) {
        $sourcesStr = '';
        foreach($this->breakpointData as $breakpointData) {
            $sourcesStr .= \Statamic::tag('glide')->params([
                'src' => $this->asset,
                'width' => $breakpointData->get('calculatedPxImageWidth'),
                'fit' => 'max',
                'quality' => '70',
                'format' => $webp ? 'webp' : null
            ]);
            $sourcesStr .= " " . $breakpointData->get('calculatedPxImageWidth') . "w,";
        }
        return $sourcesStr;
    }

    private function validateParams() {
        $validator = Validator::make($this->params->all(), [
            'src' => 'required'
        ]);

        if ($validator->fails()) {
            return throw(new \Exception(json_encode($validator->errors()->all())));
        }

        return $validator->validated();
    }

    private function isAssetOrGetAsset($entity) : \Statamic\Assets\Asset {
        $type = gettype($this->params->get('src')); // either 'string' or 'object'
        if ($type !== 'string' && $type !== 'object') {
            throw new \Exception('The src param must be a string or an object.');
        }
        // if it is an object and has a class of "Statamic\Assets\Asset" then it is an asset.
        // If it is a string, we will try to get the asset by URL.
        if ($type === 'object' && $entity instanceof \Statamic\Assets\Asset) {
            return $entity;
        } elseif ($type === 'string') {
            $asset = Asset::findByUrl($entity);
            if (!$asset) return throw new \Exception('Asset not found');
            return $asset;
        }
    }
}
