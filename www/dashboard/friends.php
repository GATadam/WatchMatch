<?php
$apiBaseUrl = 'https://kosmicdoom.com/watchmatch_api';
$searchQuery = trim($_GET['search'] ?? '');

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<div id="content">
    <h2>Friends</h2>

    <div id="friends_feedback" class="friends_feedback" hidden></div>

    <div class="friends_sections">
        <div id="friends_list" class="friends_panel">
            <h2>Friends</h2>
            <div class="friends_panel_body"></div>
        </div>

        <div id="incoming_requests" class="friends_panel">
            <h2>Friend Requests</h2>
            <div class="friends_panel_body"></div>
        </div>

        <div id="sent_requests" class="friends_panel">
            <h2>Pending Requests</h2>
            <div class="friends_panel_body"></div>
        </div>
    </div>

    <div id="search_user" class="friends_panel search_panel">
        <h2>Search User</h2>
        <form method="GET" action="dashboard.php" class="search_form" id="friends_search_form">
            <input type="hidden" name="p" value="friends">
            <input type="text" id="search_input" name="search" placeholder="Enter username" value="<?php echo h($searchQuery); ?>">
            <button id="search_button" type="submit" class="action_button">Search</button>
        </form>

        <div id="search_results"></div>
    </div>
</div>

<script>
(() => {
    const requestedApiBaseUrl = <?php echo json_encode($apiBaseUrl, JSON_UNESCAPED_SLASHES); ?>;
    const initialSearchQuery = <?php echo json_encode($searchQuery, JSON_UNESCAPED_UNICODE); ?>;
    const apiBaseUrlCandidates = Array.from(new Set([
        requestedApiBaseUrl,
        window.location.origin + '/watchmatch_api',
        'https://www.kosmicdoom.com/watchmatch_api'
    ]));

    const feedbackNode = document.getElementById('friends_feedback');
    const friendsBody = document.querySelector('#friends_list .friends_panel_body');
    const incomingBody = document.querySelector('#incoming_requests .friends_panel_body');
    const sentBody = document.querySelector('#sent_requests .friends_panel_body');
    const searchResultsNode = document.getElementById('search_results');
    const searchForm = document.getElementById('friends_search_form');
    const searchInput = document.getElementById('search_input');

    const state = {
        friends: [],
        incoming: [],
        sent: [],
        searchResults: [],
        searchQuery: initialSearchQuery || ''
    };
    let workingApiBaseUrl = null;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setFeedback(message, type = 'info') {
        if (!message) {
            feedbackNode.hidden = true;
            feedbackNode.textContent = '';
            feedbackNode.className = 'friends_feedback';
            return;
        }

        feedbackNode.hidden = false;
        feedbackNode.textContent = message;
        feedbackNode.className = 'friends_feedback ' + type;
    }

    async function requestApi(baseUrl, endpoint, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        const params = options.params || {};
        let url = baseUrl + '/' + endpoint;
        const fetchOptions = {
            method,
            credentials: 'include',
            headers: {
                'Accept': 'application/json'
            }
        };

        if (method === 'GET' && Object.keys(params).length > 0) {
            const separator = url.includes('?') ? '&' : '?';
            url += separator + new URLSearchParams(params).toString();
        }

        if (method === 'POST') {
            fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
            fetchOptions.body = new URLSearchParams(params).toString();
        }

        let response;
        try {
            response = await fetch(url, fetchOptions);
        } catch (error) {
            const networkError = new Error(error && error.message ? error.message : 'Failed to fetch');
            networkError.isRetryable = true;
            throw networkError;
        }

        const rawText = await response.text();
        const normalizedText = rawText.replace(/^\uFEFF/, '').trim();

        let payload;
        try {
            payload = JSON.parse(normalizedText);
        } catch (error) {
            const preview = normalizedText.slice(0, 200);
            const invalidResponseError = new Error(preview ? 'Invalid API response: ' + preview : 'Invalid API response.');
            invalidResponseError.isRetryable = true;
            throw invalidResponseError;
        }

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'API request failed.');
        }

        return payload;
    }

    async function apiFetch(endpoint, options = {}) {
        const candidates = workingApiBaseUrl ? [workingApiBaseUrl] : apiBaseUrlCandidates;
        let lastError = null;

        for (const baseUrl of candidates) {
            try {
                const payload = await requestApi(baseUrl, endpoint, options);
                workingApiBaseUrl = baseUrl;
                return payload;
            } catch (error) {
                lastError = error;

                if (!error.isRetryable && error.message !== 'Failed to fetch') {
                    throw error;
                }
            }
        }

        throw lastError || new Error('Failed to fetch');
    }

    function emptyStateHtml(message) {
        return '<p class="empty_state">' + escapeHtml(message) + '</p>';
    }

    function friendCardHtml(user, actionsHtml) {
        const sharedWatchedCount = Number(user && user.shared_watched_count ? user.shared_watched_count : 0);
        const friendMetaHtml = Number.isFinite(sharedWatchedCount)
            ? `<span class="friend_streak">🔥 ${sharedWatchedCount}</span>`
            : '';

        return `
            <div class="friend_profile">
                <div class="friend_icon" style="color: #${escapeHtml(user.icon_color)}; background-color: #${escapeHtml(user.icon_bg_color)};">
                    ${escapeHtml(user.profil_icon)}
                </div>
                <div class="friend_info">
                    <div class="friend_name_row">
                        <p class="friend_name">${escapeHtml(user.username)}</p>
                        ${friendMetaHtml}
                    </div>
                </div>
                <div class="friend_actions">
                    ${actionsHtml}
                </div>
            </div>
        `;
    }

    const friendActionMeta = {
        send_request:   { icon: '\u002B', cls: 'friend_btn--add',      title: 'Add friend' },
        accept_request: { icon: '\u2713', cls: 'friend_btn--accept',   title: 'Accept' },
        decline_request:{ icon: '\u2715', cls: 'friend_btn--decline',  title: 'Decline' },
        cancel_request: { icon: '\u2715', cls: 'friend_btn--cancel',   title: 'Cancel request' },
        remove_friend:  { icon: '\u2715', cls: 'friend_btn--unfriend', title: 'Unfriend' },
    };

    function actionButtonHtml(action, label, secondary = false, userId = 0) {
        const meta = friendActionMeta[action];
        if (meta) {
            return `<button type="button" class="friend_btn ${meta.cls}" data-friend-action="${escapeHtml(action)}" data-user-id="${Number(userId)}" title="${escapeHtml(meta.title)}" aria-label="${escapeHtml(meta.title)}">${meta.icon}</button>`;
        }
        const classes = secondary ? 'action_button secondary_action' : 'action_button';
        return `<button type="button" class="${classes}" data-friend-action="${escapeHtml(action)}" data-user-id="${Number(userId)}">${escapeHtml(label)}</button>`;
    }

    function renderFriends() {
        if (!state.friends.length) {
            friendsBody.innerHTML = emptyStateHtml('You do not have any friends yet.');
            return;
        }

        friendsBody.innerHTML = state.friends.map(friend =>
            friendCardHtml(friend, actionButtonHtml('remove_friend', 'Unfriend', false, friend.id))
        ).join('');
    }

    function renderIncoming() {
        if (!state.incoming.length) {
            incomingBody.innerHTML = emptyStateHtml('There are no incoming friend requests.');
            return;
        }

        incomingBody.innerHTML = state.incoming.map(request =>
            friendCardHtml(
                request,
                actionButtonHtml('accept_request', 'Accept', false, request.id) +
                actionButtonHtml('decline_request', 'Decline', true, request.id)
            )
        ).join('');
    }

    function renderSent() {
        if (!state.sent.length) {
            sentBody.innerHTML = emptyStateHtml('You do not have any outgoing friend requests.');
            return;
        }

        sentBody.innerHTML = state.sent.map(request =>
            friendCardHtml(request, actionButtonHtml('cancel_request', 'Cancel request', true, request.id))
        ).join('');
    }

    function renderSearchResults() {
        if (!state.searchQuery) {
            searchResultsNode.innerHTML = emptyStateHtml('Search for a username to find new friends.');
            return;
        }

        if (!state.searchResults.length) {
            searchResultsNode.innerHTML = emptyStateHtml(`No users found for "${state.searchQuery}".`);
            return;
        }

        const friendIds = new Set(state.friends.map(user => Number(user.id)));
        const incomingIds = new Set(state.incoming.map(user => Number(user.id)));
        const sentIds = new Set(state.sent.map(user => Number(user.id)));

        searchResultsNode.innerHTML = state.searchResults.map(result => {
            const resultId = Number(result.id);
            let actionsHtml = '';

            if (friendIds.has(resultId)) {
                actionsHtml = '<span class="status_badge">Already friends</span>';
            } else if (incomingIds.has(resultId)) {
                actionsHtml = actionButtonHtml('accept_request', 'Accept', false, resultId);
            } else if (sentIds.has(resultId)) {
                actionsHtml = actionButtonHtml('cancel_request', 'Cancel request', true, resultId);
            } else {
                actionsHtml = actionButtonHtml('send_request', 'Add friend', false, resultId);
            }

            return friendCardHtml(result, actionsHtml);
        }).join('');
    }

    async function loadSearchResults() {
        if (!state.searchQuery) {
            state.searchResults = [];
            renderSearchResults();
            return;
        }

        const payload = await apiFetch('search_users.php', {
            params: { query: state.searchQuery }
        });
        state.searchResults = payload.results || [];
        renderSearchResults();
    }

    async function loadAllData() {
        const [friendsPayload, incomingPayload, sentPayload] = await Promise.all([
            apiFetch('get_friends.php'),
            apiFetch('get_incoming_requests.php'),
            apiFetch('get_sent_requests.php')
        ]);

        state.friends = friendsPayload.friends || [];
        state.incoming = incomingPayload.incoming_requests || [];
        state.sent = sentPayload.sent_requests || [];

        renderFriends();
        renderIncoming();
        renderSent();
        await loadSearchResults();
    }

    async function performAction(action, userId) {
        const payload = await apiFetch('friend_action.php', {
            method: 'POST',
            params: {
                friend_action: action,
                target_user_id: userId
            }
        });

        setFeedback(payload.message || 'Action completed.', 'success');
        await loadAllData();
    }

    function updateSearchUrl() {
        const url = new URL(window.location.href);
        url.searchParams.set('p', 'friends');

        if (state.searchQuery) {
            url.searchParams.set('search', state.searchQuery);
        } else {
            url.searchParams.delete('search');
        }

        window.history.replaceState({}, '', url);
    }

    searchForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        state.searchQuery = searchInput.value.trim();
        updateSearchUrl();

        try {
            setFeedback('', 'info');
            await loadSearchResults();
        } catch (error) {
            setFeedback(error.message, 'error');
        }
    });

    document.getElementById('content').addEventListener('click', async (event) => {
        const button = event.target.closest('[data-friend-action]');
        if (!button) {
            return;
        }

        const action = button.getAttribute('data-friend-action');
        const userId = Number(button.getAttribute('data-user-id'));

        try {
            button.disabled = true;
            setFeedback('', 'info');
            await performAction(action, userId);
        } catch (error) {
            setFeedback(error.message, 'error');
        } finally {
            button.disabled = false;
        }
    });

    (async () => {
        try {
            await loadAllData();
            setFeedback('', 'info');
        } catch (error) {
            setFeedback(error.message, 'error');
            renderFriends();
            renderIncoming();
            renderSent();
            renderSearchResults();
        }
    })();

    setInterval(async () => {
        try {
            await loadAllData();
        } catch (error) {

        }
    }, 10000);
})();
</script>
