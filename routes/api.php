<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Models\Item;

// 預覽所有items～ (GET) http://localhost/Dressify/public/api/items => Postman - okie, 
// Route::get('/items', function () {
//     $items = Item::all();
//     return $items;
// });

// items with type & partId～ (GET) http://localhost/Dressify/public/api/items => Postman - okie, 前端 - okie 
// 瀏覽所有items
Route::get('/items', function () {
    $items = Item::with('type')->get();
    return $items;
});

// 新增一筆item～ (POST) http://localhost/Dressify/public/api/item => Postman - okie, 前端 - okie
// 新增一筆item～
Route::post('/item', function (Request $request) {
    $validated = $request->validate([
        'UID' => 'required|int|max:20',
        'Title' => 'required|string|max:8',
        'Type' => 'required|int|max:37',
        'Color' => 'nullable|string|max:5',  // 要記得同步更新item.php裡的$fillable！！ => okie
        'Size' => 'nullable|string|max:20',
        'Brand' => 'nullable|string|max:50',
        'EditedPhoto' => 'nullable|string'
    ]);
    return Item::create($validated);
});

// 查詢一筆item的所有info～ (GET) http://localhost/Dressify/public/api/item/1 => Postman - okie, 前端 - okie
// 查詢一筆item的所有info～
Route::get('/item/{ItemID}', function ($ItemID) {
    return Item::with('type')->findOrFail($ItemID);
});

// 修改一筆item～ (PUT) http://localhost/Dressify/public/api/item/15 => Postman - okie, 前端 - okie
// 修改一筆item～
Route::put('/item/{ItemID}', function (Request $request, $ItemID) {
    $item = Item::findOrFail($ItemID);
    $validated = $request->validate([
        'Title' => 'required|string|max:8',
        'Type' => 'required|int|max:37',
        'Color' => 'nullable|string|max:5',
        'Size' => 'nullable|string|max:20',
        'Brand' => 'nullable|string|max:50',
        // 'EditedPhoto' => 'nullable|string'  (暫時沒有打算讓使用者更新的時候重新上傳圖片><)
    ]);
    return $item->update($validated);  // 成功結果為1，
});

// (delete) http://localhost/Dressify/public/api/item/44 => Postman - okie,
// 刪除一筆item～
Route::delete('/item/{ItemID}', function ($ItemID) {
    Item::findOrFail($ItemID)->delete();
    return response()->json(['status' => 'succeed']);
});

use App\Models\Outfit;
// 查詢所有outfit～ (GET) http://localhost/Dressify/public/api/outfits => Postman - okie, 
// 查詢所有outfit～
Route::get('/outfits', function () {
    return Outfit::all();
});

// 搜尋Item中有沒有相似的單品（by keyword）～
// 之後也許要同時搜尋Outfit資料表？
// 一個keyword～ (GET) http://localhost/Dressify/public/api/items/search?keyword=針織 => Postman - okie, 
// 兩個keyword～ (GET) http://localhost/Dressify/public/api/items/search?keyword=Uniqlo 白 => Postman - okie, 

// 搜尋Item中有沒有相似的單品 v1（by keyword）～
// Route::get('items/search', function (Request $request) {
//     // 取得使用者輸入的關鍵字
//     $keyword = $request->input('keyword');

//     // 檢查是否有提供 keyword
//     if (!$keyword) {
//         return response()->json(['message' => '請提供搜尋關鍵字'], 400);
//     }

//     // 將多個關鍵字分割
//     $keywords = explode(' ', $keyword);

//     // 查詢多個欄位 ＊可加上color
//     $items = Item::where(function ($query) use ($keywords) {
//         foreach ($keywords as $word) {
//             $query
//                 ->orWhere('Title', 'LIKE', "%$word%")
//                 ->orWhere('Color', 'LIKE', "%$word%")
//                 ->orWhere('Brand', 'LIKE', "%$word%")
//                 ->orWhere('Size', 'LIKE', "%$word%");
//         }
//     })
//         ->orWhereHas('type', function ($query) use ($keywords) {
//             foreach ($keywords as $word) {
//                 $query->where('Name', 'LIKE', "%$word%");
//             }
//         })

//         ->with('type')  // 載入關聯的 type 資料
//         ->take(5)  // 限制取回筆數只有５筆
//         ->get();

//     // 回傳結果
//     return $items;
// });

// 搜尋tiral again - v2
Route::get('items/search', function (Request $request) {
    $keyword = $request->input('keyword');

    if (!$keyword) {
        return response()->json(['message' => '請提供搜尋關鍵字'], 400);
    }

    // 將多個關鍵字分割
    $keywords = explode(' ', $keyword);

    $items = Item::where(function ($query) use ($keywords) {
        // 對每個關鍵字進行搜尋
        foreach ($keywords as $word) {
            $query->orWhere('Title', 'LIKE', "%$word%")
                  ->orWhere('Color', 'LIKE', "%$word%")
                  ->orWhere('Brand', 'LIKE', "%$word%")
                  ->orWhere('Size', 'LIKE', "%$word%");
        }
    })
    ->where(function ($query) use ($keywords) {
        // 聚焦：要求所有關鍵字至少在 `Title` 中匹配一次
        foreach ($keywords as $word) {
            $query->where('Title', 'LIKE', "%$word%");
        }
    })
    ->orWhereHas('type', function ($query) use ($keywords) {
        // 檢查關聯的類型名稱是否包含所有關鍵字
        foreach ($keywords as $word) {
            $query->where('Name', 'LIKE', "%$word%");
        }
    })
    ->with('type')  // 載入關聯的 type 資料
    ->orderByRaw("FIELD(Title, ?)", [$keyword]) // 聚焦排序（把最相關的放前面）
    ->take(5)  // 限制取回筆數只有 5 筆
    ->get();

    return $items;
});

// method 2 => not working
// 在Item資料表中，輸入以下指令：在 Title、Brand、Size 欄位上建立全文索引
// ALTER TABLE Item ADD FULLTEXT(Title, Brand, Size);
// QQ這個要四個字以上關鍵字才能找，要改要到mysql設定檔使用root權限修改
// Route::get('items/search', function (Request $request) {
//     // 取得使用者輸入的關鍵字
//     $keyword = $request->input('keyword');

//     // 檢查是否有提供 keyword
//     if (!$keyword) {
//         return response()->json(['message' => '請提供搜尋關鍵字'], 400);
//     }

//     $items = Item::whereRaw("MATCH(Title, Brand, Size) AGAINST(? IN BOOLEAN MODE)", ["$keyword*"])
//         ->with('type')
//         ->get();

//     return $items;
// });

// (GET) http://localhost/Dressify/public/api/item/1/outfits => Postman - okie ,
// 單品有在哪些outfit中被使用ㄉapi
Route::get('/item/{ItemID}/outfits', function ($ItemID) {
    // 查找指定 Item 的所有相關 Outfit
    $item = Item::with('outfits.items') // 同時載入相關的 Outfit 和 Outfit 中的其他 Items
        ->findOrFail($ItemID);

    $relatedOutfits = $item->outfits->map(function ($outfit) {
        return [
            'OutfitID' => $outfit->OutfitID,
            'OutfitTitle' => $outfit->Title,
            'ItemsInOutfit' => $outfit->items->map(function ($relatedItem) {
                return [
                    'ItemID' => $relatedItem->ItemID,
                    // 'Title' => $relatedItem->Title,
                    'EditedPhoto' => $relatedItem->EditedPhoto,
                ];
            }),
        ];
    });

    return response()->json($relatedOutfits);
});

use App\Models\Post;
use App\Models\TagList;

// (GET) http://localhost/Dressify/public/api/item/1/recomms => Postman - okie
// (DET) http://localhost/Dressify/public/api/item/14/recomms => Postman - okie
// ItemID = 14 才有posts的資料
// 我是覺得搜尋similarItems的部分還需要微調邏輯=.=
// 單品有哪些相似的穿搭可以在dresswall被看到
Route::get('/item/{ItemID}/recomms', function ($ItemID) {
    // 假設已經從登入用戶的 Session 或 Token 取得 UID
    // (Request $request)
    // $currentUID = $request->user()->UID;
    $currentUID = 1;  // 測試用 UID，正式應從認證機制獲取

    // 找到該單品
    $item = Item::findOrFail($ItemID);

    // 使用該單品的 Title、Size、Brand 等條件來搜尋相似單品
    $similarItems = Item::where('ItemID', '!=', $item->ItemID)
        ->where(function ($query) use ($item) {
            $query->where('Title', 'LIKE', '%' . $item->Title . '%')
                // ->orWhere('Color', $item->Color)  // 因為目前很多顏色都是nullＱＱ
                ->orWhere('Size', $item->Size)
                ->orWhere('Brand', $item->Brand);
        })
        ->get();

    // 找到這些單品相關的 Outfit
    $outfitIds = TagList::whereIn('ItemID', $similarItems->pluck('ItemID'))
        ->distinct()  // 確保資料不重複
        ->pluck('OutfitID');
    // ->unique();

    // 過濾出 UID 不等於當前用戶的 Outfit，並載入 Member 資料
    $outfits = Outfit::whereIn('OutfitID', $outfitIds)
        ->where('UID', '!=', $currentUID)
        ->with(['member' => function ($query) {
            $query->select('UID', 'UserName', 'Avatar');
        }])
        ->get();

    // 找到符合條件的 Post 並載入相關的 Outfit
    $posts = Post::whereIn('OutfitID', $outfits->pluck('OutfitID'))
        ->with(['outfit.member' => function ($query) {
            $query->select('UID', 'UserName', 'Avatar'); // 確保 Post 關聯的 Outfit 也載入 Member的三個欄位
        }])
        ->get();

    return response()->json([
        'similar_items' => $similarItems,
        'outfit_ids' => $outfitIds,
        'posts' => $posts,
    ]);
});
