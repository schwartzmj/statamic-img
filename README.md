# Image Components

> statamic-img generates responsive and WebP images (for browsers that support it). It uses Tailwind breakpoints.

## Features

WIP

## How to Install

<!-- You can search for this addon in the `Tools > Addons` section of the Statamic control panel and click **install**, or run the following command from your project root: -->

```bash
composer require schwartzmj/statamic-img
```

## How to Use

```
{{ img
    src="path/to/asset || asset entry"
    priority="true" // optional. By default this is set to 'lazy', but setting priority will set it to 'eager', instructing the browser to load it ASAP. (Use for LCP images)
    sizes="100 md:50 xl:33" // separate breakpoints with a space. preface the breakpoint with "md:", "lg:" etc. an image will be generate that fits the proper screen width
    alt=""// optionally override the asset's alt tag
    ...="" // any other attributes
}}
```
