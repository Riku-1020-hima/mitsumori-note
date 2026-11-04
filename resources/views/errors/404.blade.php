<x-error-page code="404" title="ページが見つかりません">
    <p>開こうとしたページは、ないか、もう使えなくなっています。</p>

    <h2>こうすると直ります</h2>
    <ol>
        <li>URLが途中で切れていないか確かめる(LINEなどからコピーしたときに起きやすいです)</li>
        <li>削除した書類や取引先のページを開こうとしていないか確かめる</li>
        <li>下の「ホームを開く」から、もう一度たどってください</li>
    </ol>

    <div class="actions">
        <a class="btn primary" href="{{ url('/') }}">ホームを開く</a>
        <button type="button" class="btn" onclick="history.back()">前の画面に戻る</button>
    </div>
</x-error-page>
