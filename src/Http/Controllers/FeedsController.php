<?php

namespace Daalder\Feeds\Http\Controllers;

use Daalder\Feeds\Http\Requests\StoreFeedsRequest;
use Daalder\Feeds\Services\FeedsHandler;
use Illuminate\Http\JsonResponse;
use Pionect\Daalder\Http\Controllers\BaseController;

class FeedsController extends BaseController
{
    public function index()
    {
        return view("daalder-feeds::feeds.feeds");
    }

    /**
     * @param StoreFeedsRequest $request
     * @param FeedsHandler $feedsHandler
     * @return JsonResponse
     * @throws \Throwable
     */
    public function store(StoreFeedsRequest $request, FeedsHandler $feedsHandler): JsonResponse
    {
        $feedsHandler->generateFeeds($request->validated());

        return response()->json(true);
    }
}
