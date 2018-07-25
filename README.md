# processor-create-manifest

[![Build Status](https://travis-ci.org/keboola/processor-create-manifest.svg?branch=master)](https://travis-ci.org/keboola/processor-create-manifest)

Takes all CSV files in `/data/in/tables` and creates or updates the manifest file move all files to `/data/out/tables`. 

Adds or updates these manifest attributes

 - `delimiter`, `enclosure` -- default, or from configuration
 - `columns` -- passed array of columns from configuration or autodetect (`columns_from` parameter)
 - `primary_key` -- passed array of columns
 - `incremental` -- from configuration  
 
## Development
 
Clone this repository and init the workspace with following command:

```
git clone https://github.com/keboola/processor-create-manifest
cd processor-create-manifest
docker-compose build
docker-compose run dev composer install
```

Run the test suite using this command:

```
docker-compose run dev composer ci
```
 
# Integration
 - Build is started after push on [Travis CI](https://travis-ci.org/keboola/processor-create-manifest)
 - [Build steps](https://github.com/keboola/processor-create-manifest/blob/master/.travis.yml)
   - build image
   - execute tests against new image
   - publish image to ECR if release is tagged
   
# Usage
It supports optional parameters:

 - `delimiter` -- CSV delimiter, defaults to `,`
 - `enclosure` -- CSV enclosure, defaults to `"`
 - `columns` -- Array of column names
 - `columns_from` (`header`, `auto`) -- Populates the `columns` attribute
   - `header` -- Uses the first line of the CSV file (or of any of the slices) as the column names, if the headers are empty, then auto-generated names are used.
   - `auto` -- Creates the column names automatically as a sequence, starting with `col_1` 
 - `primary_key` -- Array of column names
 - `incremental` -- `true` or `false`

## Sample configurations

Default parameters:

```
{  
    "definition": {
        "component": "keboola.processor-create-manifest"
    }
}
```

Add column names:

```
{
    "definition": {
        "component": "keboola.processor-create-manifest"
    },
    "parameters": {
        "columns": ["id", "amount"]
    }
}

```

Set delimiter and enclosure:

```
{
    "definition": {
        "component": "keboola.processor-create-manifest"
    },
    "parameters": {
        "delimiter": "\t",
        "enclosure": "'"
    }
}

```
