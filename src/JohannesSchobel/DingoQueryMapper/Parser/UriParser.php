<?php

namespace JohannesSchobel\DingoQueryMapper\Parser;

use Illuminate\Http\Request;

class UriParser
{
    /**
     * @var Request the given request
     */
    protected $request;

    /**
     * @var string the available compare operators
     */
    protected $pattern = '/!=|=|<=|<|>=|>/';

    /**
     * @var array the keywords which are handled individually
     */
    protected $predefinedParams = [
        'sort',
        'limit',
        'page',
        //'columns',
        //'rels',
    ];

    /**
     * @var string the request uri
     */
    protected $uri;

    /**
     * @var string the path of the uri
     */
    protected $path;

    /**
     * @var string the query string (already encoded)
     */
    protected $query;

    /**
     * @var array the extracted query parameters
     */
    protected $queryParameters = [];

    /**
     * UriParser constructor.
     *
     * @param Request $request the given request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;

        $this->uri = $request->getRequestUri();

        $this->path = $request->getPathInfo();

        $this->query = rawurldecode($request->getQueryString());

        if ($this->hasQueryUri()) {
            $this->setQueryParameters($this->query);
        }
    }

    /**
     * Gets the respective parameter
     *
     * @param $key
     * @return mixed
     */
    public function queryParameter($key)
    {
        $keys = array_pluck($this->queryParameters, 'key');
        $queryParameters = array_combine($keys, $this->queryParameters);

        return $queryParameters[$key];
    }

    /**
     * Gets the predefined parameters
     *
     * @return array
     */
    public function predefinedParameters()
    {
        return $this->predefinedParams;
    }

    /**
     * Gets the WHERE parameters
     *
     * @return array
     */
    public function whereParameters()
    {
        return array_filter(
            $this->queryParameters,
            function ($queryParameter) {
                $key = $queryParameter['key'];
                return (!in_array($key, $this->predefinedParams));
            }
        );
    }

    protected function parseFilter($filterParam)
    {
        $supportedPostfixes = [
            'st' => '<',
            'gt' => '>',
            'min' => '>=',
            'max' => '<=',
            'lk' => 'like',
            'not-lk' => 'not like',
            'in' => 'IN',
            'not-in' => 'NOT IN',
            'not' => '!=',
        ];
        $supportedPrefixesStr = implode('|', $supportedPostfixes);
        $supportedPostfixesStr = implode('|', array_keys($supportedPostfixes));

        list($filterParamKey, $filterParamValue) = explode('=', $filterParam);
        $keyMatches = [];
        //Matches every parameter with an optional prefix and/or postfix
        //e.g. not-title-lk, title-lk, not-title, title
        $keyRegex = '/^(?:(' . $supportedPrefixesStr . ')-)?(.*?)(?:-(' . $supportedPostfixesStr . ')|$)/';
        preg_match($keyRegex, $filterParamKey, $keyMatches);
        if (!isset($keyMatches[3])) {
            if (strtolower(trim($filterParamValue)) == 'null') {
                $comparator = 'NULL';
            } else {
                $comparator = '=';
            }
        } else {
            if (strtolower(trim($filterParamValue)) == 'null') {
                $comparator = 'NOT NULL';
            } else {
                $comparator = $supportedPostfixes[$keyMatches[3]];
            }
        }

        array_push($this->queryParameters, [
            'key' => $keyMatches[2],
            'operator' => $comparator,
            'value' => $filterParamValue
        ]);
    }

    /**
     * Sets the query parameters
     *
     * @param $query
     */
    private function setQueryParameters($query)
    {
        $queryParameters = array_filter(explode('&', $query));
        array_map([$this, 'parseFilter'], $queryParameters);
    }

    /**
     * Checks if the URI has a query string appended
     *
     * @return string
     */
    protected function hasQueryUri()
    {
        return ($this->query);
    }

    /**
     * Checks if the URI has query parameters
     * @return bool
     */
    public function hasQueryParameters()
    {
        return (count($this->queryParameters) > 0);
    }

    /**
     * Checks, if the given query parameter exists
     *
     * @param $key
     * @return bool
     */
    public function hasQueryParameter($key)
    {
        $keys = array_pluck($this->queryParameters, 'key');

        return (in_array($key, $keys));
    }
}
