<?php
$apiBaseUrl = 'https://kosmicdoom.com/watchmatch_api';
?>

<div id="content" class="match_online_page">
    <h2>Match Online</h2>

    <div id="match_online_feedback" class="friends_feedback" hidden></div>

    <div id="match_online_lobby">
        <div class="match_online_choice_grid">
            <button type="button" class="match_online_choice active" data-match-view="create">
                <span class="match_online_choice_title">Create a room</span>
                <span class="match_online_choice_text">Pick providers from your region and open a room for a friend.</span>
            </button>
            <button type="button" class="match_online_choice" data-match-view="join">
                <span class="match_online_choice_title">Join a friend</span>
                <span class="match_online_choice_text">See which friends already opened a room and jump in instantly.</span>
            </button>
        </div>

        <div id="match_online_resume_panel" class="match_online_panel" hidden>
            <div class="match_online_panel_header">
                <div>
                    <h3>Resume current room</h3>
                    <p id="match_online_resume_text" class="match_online_helper"></p>
                </div>
                <button type="button" class="action_button" id="match_online_resume_room_btn">Resume room</button>
            </div>
            <div id="match_online_resume_meta" class="match_online_room_meta match_online_resume_meta"></div>
        </div>

        <div id="match_online_create_panel" class="match_online_panel">
            <div class="match_online_panel_header">
                <div>
                    <h3>Create a room</h3>
                    <p id="match_online_region_info" class="match_online_helper"></p>
                </div>
                <div class="match_online_provider_tools">
                    <button type="button" class="secondary_action action_button" id="match_online_select_all">Select all</button>
                    <button type="button" class="secondary_action action_button" id="match_online_clear_all">Clear</button>
                </div>
            </div>

            <div id="match_online_provider_list" class="match_online_provider_list"></div>

            <div class="match_online_panel_footer">
                <span id="match_online_provider_count" class="status_badge">0 providers selected</span>
                <button type="button" class="action_button" id="match_online_create_room_btn">Create room</button>
            </div>
        </div>

        <div id="match_online_join_panel" class="match_online_panel" hidden>
            <div class="match_online_panel_header">
                <div>
                    <h3>Join a friend</h3>
                    <p class="match_online_helper">Only rooms opened by confirmed friends are shown here.</p>
                </div>
                <button type="button" class="secondary_action action_button" id="match_online_refresh_rooms_btn">Refresh rooms</button>
            </div>

            <div id="match_online_joinable_rooms"></div>
        </div>
    </div>

    <div id="match_online_room_panel" class="match_online_panel" hidden>
        <div class="match_room_header">
            <div>
                <p class="match_online_helper">Current room</p>
                <h3 id="match_online_room_title">Room</h3>
            </div>
            <span id="match_online_room_status" class="status_badge"></span>
        </div>

        <div id="match_online_room_meta" class="match_online_room_meta"></div>
        <div id="match_online_room_participants" class="match_online_room_participants"></div>

        <div id="match_online_waiting_state" class="match_online_waiting_state" hidden>
            <h3>Waiting for a friend</h3>
            <p id="match_online_waiting_text" class="match_online_helper"></p>
        </div>

        <div id="match_online_swipe_state" class="match_online_swipe_state" hidden>
            <div class="match_swipe_board">
                <div class="match_card_stack" id="match_online_card_stack"></div>
                <div class="match_swipe_controls">
                    <button type="button" class="secondary_action action_button match_swipe_btn" data-swipe-direction="left">Swipe left</button>
                    <button type="button" class="action_button match_swipe_btn" data-swipe-direction="right">Swipe right</button>
                </div>
            </div>
        </div>
    </div>

    <div id="match_online_match_overlay" class="match_online_match_overlay" hidden>
        <div class="match_online_match_modal">
            <p class="match_online_helper">You have a match</p>
            <h3 id="match_online_match_title"></h3>
            <div id="match_online_match_poster" class="match_online_match_poster"></div>
            <div id="match_online_match_providers" class="match_online_provider_row match_online_match_providers"></div>
            <p id="match_online_match_status" class="match_online_helper"></p>
            <div class="match_online_match_actions">
                <button type="button" class="secondary_action action_button" data-match-decision="keep_swiping">Keep swiping</button>
                <button type="button" class="action_button" data-match-decision="lets_watch">Let's watch</button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const requestedApiBaseUrl = <?php echo json_encode($apiBaseUrl, JSON_UNESCAPED_SLASHES); ?>;
    const apiBaseUrlCandidates = Array.from(new Set([
        requestedApiBaseUrl,
        window.location.origin + '/watchmatch_api',
        'https://www.kosmicdoom.com/watchmatch_api'
    ]));

    const dom = {
        root: document.getElementById('content'),
        feedback: document.getElementById('match_online_feedback'),
        lobby: document.getElementById('match_online_lobby'),
        roomPanel: document.getElementById('match_online_room_panel'),
        resumePanel: document.getElementById('match_online_resume_panel'),
        resumeText: document.getElementById('match_online_resume_text'),
        resumeMeta: document.getElementById('match_online_resume_meta'),
        resumeRoomBtn: document.getElementById('match_online_resume_room_btn'),
        createPanel: document.getElementById('match_online_create_panel'),
        joinPanel: document.getElementById('match_online_join_panel'),
        providerList: document.getElementById('match_online_provider_list'),
        providerCount: document.getElementById('match_online_provider_count'),
        regionInfo: document.getElementById('match_online_region_info'),
        createRoomBtn: document.getElementById('match_online_create_room_btn'),
        selectAllBtn: document.getElementById('match_online_select_all'),
        clearAllBtn: document.getElementById('match_online_clear_all'),
        refreshRoomsBtn: document.getElementById('match_online_refresh_rooms_btn'),
        joinableRooms: document.getElementById('match_online_joinable_rooms'),
        roomTitle: document.getElementById('match_online_room_title'),
        roomStatus: document.getElementById('match_online_room_status'),
        roomMeta: document.getElementById('match_online_room_meta'),
        roomParticipants: document.getElementById('match_online_room_participants'),
        waitingState: document.getElementById('match_online_waiting_state'),
        waitingText: document.getElementById('match_online_waiting_text'),
        swipeState: document.getElementById('match_online_swipe_state'),
        cardStack: document.getElementById('match_online_card_stack'),
        overlay: document.getElementById('match_online_match_overlay'),
        overlayTitle: document.getElementById('match_online_match_title'),
        overlayPoster: document.getElementById('match_online_match_poster'),
        overlayProviders: document.getElementById('match_online_match_providers'),
        overlayStatus: document.getElementById('match_online_match_status'),
        viewButtons: Array.from(document.querySelectorAll('[data-match-view]')),
        swipeButtons: Array.from(document.querySelectorAll('[data-swipe-direction]')),
        decisionButtons: Array.from(document.querySelectorAll('[data-match-decision]'))
    };

    const state = {
        lobby: null,
        currentRoom: null,
        resumableRoom: null,
        selectedView: 'create',
        selectedProviderIds: new Set(),
        workingApiBaseUrl: null,
        roomPoller: null,
        busy: false,
        drag: null
    };

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function resolveMediaUrl(value) {
        const raw = String(value || '').trim();
        if (!raw) {
            return '';
        }

        if (/^https?:\/\//i.test(raw)) {
            return raw;
        }

        if (raw.startsWith('//')) {
            return window.location.protocol + raw;
        }

        if (raw.startsWith('/')) {
            return window.location.origin + raw;
        }

        return window.location.origin + '/' + raw.replace(/^\.?\//, '');
    }

    function setFeedback(message, type = 'info') {
        if (!message) {
            dom.feedback.hidden = true;
            dom.feedback.textContent = '';
            dom.feedback.className = 'friends_feedback';
            return;
        }

        dom.feedback.hidden = false;
        dom.feedback.textContent = message;
        dom.feedback.className = 'friends_feedback ' + type;
    }

    function wait(ms) {
        return new Promise((resolve) => window.setTimeout(resolve, ms));
    }

    function canUseTouchDrag() {
        return window.matchMedia('(pointer: coarse)').matches || navigator.maxTouchPoints > 0;
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
        const candidates = state.workingApiBaseUrl ? [state.workingApiBaseUrl] : apiBaseUrlCandidates;
        let lastError = null;

        for (const baseUrl of candidates) {
            try {
                const payload = await requestApi(baseUrl, endpoint, options);
                state.workingApiBaseUrl = baseUrl;
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

    function providerLogoHtml(provider) {
        const logoUrl = resolveMediaUrl(provider && provider.logo ? provider.logo : '');
        if (!logoUrl) {
            return `<span class="match_online_provider_logo is-fallback">${escapeHtml((provider && provider.name ? provider.name : '?').charAt(0).toUpperCase())}</span>`;
        }

        return `<img class="match_online_provider_logo" src="${escapeHtml(logoUrl)}" alt="${escapeHtml(provider.name)} logo" loading="lazy">`;
    }

    function providerBadgeHtml(provider) {
        return `
            <span class="match_online_provider_badge">
                ${providerLogoHtml(provider)}
                <span>${escapeHtml(provider.name)}</span>
            </span>
        `;
    }

    function userAvatarHtml(user) {
        if (!user) {
            return `
                <div class="match_online_participant is-empty">
                    <div class="friend_icon">?</div>
                    <div>
                        <p class="friend_name">Waiting...</p>
                        <p class="match_online_helper">No friend joined yet</p>
                    </div>
                </div>
            `;
        }

        return `
            <div class="match_online_participant">
                <div class="friend_icon" style="color: #${escapeHtml(user.icon_color)}; background-color: #${escapeHtml(user.icon_bg_color)};">
                    ${escapeHtml(user.profil_icon)}
                </div>
                <div>
                    <p class="friend_name">${escapeHtml(user.username)}</p>
                </div>
            </div>
        `;
    }

    function roomStatusLabel(status) {
        if (status === 'waiting') {
            return 'Waiting';
        }

        if (status === 'active') {
            return 'Planning together';
        }

        if (status === 'matched') {
            return 'Match found';
        }

        if (status === 'closed') {
            return 'Finished';
        }

        return status;
    }

    function cardHtml(movie, index) {
        const offset = index * 10;
        const scale = 1 - index * 0.04;
        const baseTransform = `translateY(${offset}px) scale(${scale})`;
        const pictureUrl = resolveMediaUrl(movie.picture);
        return `
            <article class="match_online_card ${index === 0 ? 'is-top' : ''}" data-movie-id="${Number(movie.id)}" data-base-transform="${escapeHtml(baseTransform)}" style="transform: ${escapeHtml(baseTransform)}; z-index: ${20 - index};">
                <div class="match_online_card_visual" style="background-image: url('${escapeHtml(pictureUrl)}');"></div>
                <div class="match_online_card_content">
                    <p class="match_online_helper">Popularity ${Math.round(Number(movie.popularity || 0))}</p>
                    <h3>${escapeHtml(movie.title)}</h3>
                </div>
            </article>
        `;
    }

    function updateViewButtons() {
        dom.viewButtons.forEach((button) => {
            button.classList.toggle('active', button.getAttribute('data-match-view') === state.selectedView);
        });
        dom.createPanel.hidden = state.selectedView !== 'create';
        dom.joinPanel.hidden = state.selectedView !== 'join';
    }

    function updateProviderCount() {
        const count = state.selectedProviderIds.size;
        dom.providerCount.textContent = count === 1 ? '1 provider selected' : `${count} providers selected`;
    }

    function renderCreatePanel() {
        if (!state.lobby) {
            return;
        }

        dom.regionInfo.textContent = `Room region: ${state.lobby.region.name} (${state.lobby.region.iso_code})`;

        if (!state.lobby.providers.length) {
            dom.providerList.innerHTML = '<p class="empty_state">No providers found for your region.</p>';
            updateProviderCount();
            return;
        }

        dom.providerList.innerHTML = state.lobby.providers.map((provider) => {
            const checked = state.selectedProviderIds.has(Number(provider.id)) ? 'checked' : '';
            return `
                <label class="match_online_provider_option">
                    <input type="checkbox" value="${Number(provider.id)}" ${checked}>
                    ${providerLogoHtml(provider)}
                    <span>${escapeHtml(provider.name)}</span>
                </label>
            `;
        }).join('');

        updateProviderCount();
    }

    function renderJoinPanel() {
        if (!state.lobby) {
            return;
        }

        if (!state.lobby.joinable_rooms.length) {
            dom.joinableRooms.innerHTML = '<p class="empty_state">None of your friends has an open room right now.</p>';
            return;
        }

        dom.joinableRooms.innerHTML = state.lobby.joinable_rooms.map((room) => `
            <div class="match_online_join_card">
                <div class="match_online_join_summary">
                    ${userAvatarHtml(room.host)}
                    <div class="match_online_join_meta">
                        <p class="match_online_helper">Region: ${escapeHtml(room.region.name)}</p>
                        <div class="match_online_provider_row">
                            ${room.providers.map(providerBadgeHtml).join('')}
                        </div>
                    </div>
                </div>
                <button type="button" class="action_button" data-join-room="${Number(room.id)}">Join</button>
            </div>
        `).join('');
    }

    function renderResumePanel() {
        const room = state.resumableRoom;
        if (!room) {
            dom.resumePanel.hidden = true;
            dom.resumeText.textContent = '';
            dom.resumeMeta.innerHTML = '';
            return;
        }

        dom.resumePanel.hidden = false;
        dom.resumeText.textContent = room.status === 'waiting'
            ? 'You already opened a room. Resume it when you are ready to wait for your friend or continue from there.'
            : 'You already have an active room with a friend. Resume it to keep swiping where you left off.';
        dom.resumeMeta.innerHTML = `
            <span class="status_badge">Status: ${escapeHtml(room.status)}</span>
            <span class="status_badge">Region: ${escapeHtml(room.region.name)}</span>
            ${room.providers.map(providerBadgeHtml).join('')}
        `;
    }

    function stopRoomPolling() {
        if (state.roomPoller) {
            clearInterval(state.roomPoller);
            state.roomPoller = null;
        }
    }

    function startRoomPolling() {
        stopRoomPolling();

        if (!state.currentRoom || !state.currentRoom.id) {
            return;
        }

        state.roomPoller = setInterval(async () => {
            if (state.busy || !state.currentRoom || !state.currentRoom.id) {
                return;
            }

            try {
                const payload = await apiFetch('get_match_online_room_state.php', {
                    params: { room_id: state.currentRoom.id }
                });
                handleRoomPayload(payload.room, '');
            } catch (error) {
                setFeedback(error.message, 'error');
            }
        }, 4000);
    }

    function resetDrag(restoreTransform = true) {
        if (!state.drag) {
            return;
        }

        const { card, moveHandler, upHandler } = state.drag;
        window.removeEventListener('pointermove', moveHandler);
        window.removeEventListener('pointerup', upHandler);
        window.removeEventListener('pointercancel', upHandler);
        if (card) {
            card.classList.remove('is-dragging');
            if (restoreTransform) {
                card.style.transform = card.getAttribute('data-base-transform') || '';
                card.style.opacity = '';
            }
        }
        state.drag = null;
    }

    function getTopCard() {
        return dom.cardStack.querySelector('.match_online_card.is-top');
    }

    async function animateCardExit(card, direction, startX = 0) {
        if (!card) {
            return;
        }

        const sign = direction === 'right' ? 1 : -1;
        const viewportOffset = Math.max(window.innerWidth, 900);
        const targetX = sign * (viewportOffset + Math.abs(startX));
        const rotation = sign * 22;

        card.classList.remove('is-dragging');
        card.classList.add('is-swiping-out');

        await new Promise((resolve) => {
            const handleTransitionEnd = (event) => {
                if (event.target !== card) {
                    return;
                }

                card.removeEventListener('transitionend', handleTransitionEnd);
                resolve();
            };

            card.addEventListener('transitionend', handleTransitionEnd);

            requestAnimationFrame(() => {
                card.style.transform = `translateX(${targetX}px) rotate(${rotation}deg)`;
                card.style.opacity = '0';
            });
        });
    }

    async function fetchRoomState(roomId) {
        const payload = await apiFetch('get_match_online_room_state.php', {
            params: { room_id: roomId }
        });
        return payload.room;
    }

    async function settleDecision(room) {
        if (!room || room.status !== 'matched' || !room.self_decision || room.other_decision) {
            return room;
        }

        const deadline = Date.now() + 2800;
        while (Date.now() < deadline) {
            await wait(350);
            const updatedRoom = await fetchRoomState(room.id);
            if (updatedRoom.status !== 'matched' || updatedRoom.other_decision) {
                return updatedRoom;
            }
        }

        return room;
    }

    async function performSwipe(direction, card = null, startX = 0) {
        if (!state.currentRoom || !state.currentRoom.current_movies || !state.currentRoom.current_movies.length || state.busy) {
            return;
        }

        const activeCard = card || getTopCard();
        const movie = state.currentRoom.current_movies[0];

        try {
            state.busy = true;
            if (activeCard) {
                await animateCardExit(activeCard, direction, startX);
            }

            const payload = await apiFetch('submit_match_online_swipe.php', {
                method: 'POST',
                params: {
                    room_id: state.currentRoom.id,
                    movie_id: movie.id,
                    swipe: direction
                }
            });

            handleRoomPayload(payload.room, payload.message || 'Swipe saved.');
        } catch (error) {
            setFeedback(error.message, 'error');
            renderRoom();
        } finally {
            state.busy = false;
        }
    }

    function bindTopCardDrag() {
        resetDrag();

        const topCard = getTopCard();
        if (!topCard || !state.currentRoom || state.currentRoom.status !== 'active' || !canUseTouchDrag()) {
            return;
        }

        topCard.addEventListener('pointerdown', (event) => {
            if (state.busy || (event.pointerType && event.pointerType !== 'touch')) {
                return;
            }

            event.preventDefault();
            topCard.setPointerCapture(event.pointerId);
            const startX = event.clientX;

            const moveHandler = (moveEvent) => {
                const deltaX = moveEvent.clientX - startX;
                const rotation = deltaX / 18;
                topCard.classList.add('is-dragging');
                topCard.style.transform = `translateX(${deltaX}px) rotate(${rotation}deg)`;
            };

            const upHandler = async (upEvent) => {
                const deltaX = upEvent.clientX - startX;

                if (deltaX > 140) {
                    resetDrag(false);
                    await performSwipe('right', topCard, deltaX);
                } else if (deltaX < -140) {
                    resetDrag(false);
                    await performSwipe('left', topCard, deltaX);
                } else {
                    resetDrag(true);
                    bindTopCardDrag();
                }
            };

            state.drag = {
                card: topCard,
                moveHandler,
                upHandler
            };

            window.addEventListener('pointermove', moveHandler);
            window.addEventListener('pointerup', upHandler);
            window.addEventListener('pointercancel', upHandler);
        }, { once: true });
    }

    function renderSwipeState() {
        const room = state.currentRoom;
        if (room && room.status === 'matched' && room.matched_movie) {
            dom.cardStack.innerHTML = cardHtml(room.matched_movie, 0);
            return;
        }

        const movies = state.currentRoom && Array.isArray(state.currentRoom.current_movies)
            ? state.currentRoom.current_movies
            : [];

        if (!movies.length) {
            dom.cardStack.innerHTML = `
                <div class="match_online_empty_stack">
                    <p class="match_online_helper">No more movies are available with the selected providers right now.</p>
                </div>
            `;
            bindTopCardDrag();
            return;
        }

        dom.cardStack.innerHTML = movies
            .slice(0, 3)
            .map((movie, index) => cardHtml(movie, index))
            .reverse()
            .join('');

        bindTopCardDrag();
    }

    function renderMatchOverlay() {
        const room = state.currentRoom;
        if (!room || room.status !== 'matched' || !room.matched_movie) {
            dom.overlay.hidden = true;
            return;
        }

        dom.overlay.hidden = false;
        dom.overlayTitle.textContent = room.matched_movie.title;
        dom.overlayPoster.innerHTML = `
            <img src="${escapeHtml(resolveMediaUrl(room.matched_movie.picture))}" alt="${escapeHtml(room.matched_movie.title)}">
        `;
        const providers = Array.isArray(room.matched_movie.providers) ? room.matched_movie.providers : [];
        dom.overlayProviders.innerHTML = providers.length
            ? providers.map(providerBadgeHtml).join('')
            : '<span class="match_online_helper">Provider info unavailable.</span>';

        let statusText = 'You both liked this film.';
        if (providers.length) {
            const providerNames = providers.map((provider) => provider.name).join(', ');
            statusText += ` Watch it on: ${providerNames}.`;
        }
        if (room.self_decision && !room.other_decision) {
            statusText = room.self_decision === 'lets_watch'
                ? 'Your choice is saved. Finalizing the movie plan...'
                : 'Your choice is saved. Waiting for your friend.';
        } else if (!room.self_decision && room.other_decision) {
            statusText = 'Your friend already decided. Choose what you want to do.';
        } else if (room.self_decision && room.other_decision) {
            statusText = 'Finalizing your shared plan...';
        }
        dom.overlayStatus.textContent = statusText;

        dom.decisionButtons.forEach((button) => {
            button.classList.toggle('active', button.getAttribute('data-match-decision') === room.self_decision);
        });
    }

    function renderRoom() {
        const room = state.currentRoom;
        if (!room) {
            dom.roomPanel.hidden = true;
            dom.overlay.hidden = true;
            dom.lobby.hidden = false;
            dom.root.classList.remove('is-swipe-mode');
            return;
        }

        dom.lobby.hidden = true;
        dom.roomPanel.hidden = false;
        dom.root.classList.toggle('is-swipe-mode', room.status === 'active' || room.status === 'matched');

        dom.roomTitle.textContent = room.status === 'waiting' ? 'Waiting room' : 'Shared movie plan';
        dom.roomStatus.textContent = roomStatusLabel(room.status);

        dom.roomMeta.innerHTML = `
            <span class="status_badge">Region: ${escapeHtml(room.region.name)}</span>
            ${room.providers.map(providerBadgeHtml).join('')}
        `;

        if (room.status === 'waiting') {
            dom.roomParticipants.hidden = false;
            dom.roomParticipants.innerHTML = `
                ${userAvatarHtml(room.host)}
                ${userAvatarHtml(room.guest)}
            `;
            dom.waitingState.hidden = false;
            dom.swipeState.hidden = true;
            dom.waitingText.textContent = 'This room is open. Your selected providers are saved, and your friend can join from the Join a friend list.';
            dom.overlay.hidden = true;
            return;
        }

        dom.roomParticipants.hidden = true;
        dom.waitingState.hidden = true;

        if (room.status === 'active' || room.status === 'matched') {
            dom.swipeState.hidden = false;
            renderSwipeState();
            renderMatchOverlay();
            return;
        }

        dom.swipeState.hidden = true;
        dom.overlay.hidden = true;

        if (room.status === 'closed') {
            setFeedback('This room has been closed.', 'success');
        }
    }

    function renderLobby() {
        dom.roomPanel.hidden = true;
        dom.overlay.hidden = true;
        dom.lobby.hidden = false;
        dom.root.classList.remove('is-swipe-mode');
        renderResumePanel();
        updateViewButtons();
        renderCreatePanel();
        renderJoinPanel();
    }

    async function loadLobby() {
        const payload = await apiFetch('get_match_online_lobby.php');
        state.lobby = payload;

        if (!state.selectedProviderIds.size && payload.providers.length) {
            state.selectedProviderIds = new Set(payload.providers.map((provider) => Number(provider.id)));
        }

        state.resumableRoom = payload.current_room || null;
        state.currentRoom = null;
        stopRoomPolling();
        renderLobby();
    }

    function handleRoomPayload(room, message = '') {
        state.currentRoom = room;

        if (room.status === 'closed') {
            stopRoomPolling();
            state.currentRoom = null;
            loadLobby().catch((error) => setFeedback(error.message, 'error'));
            if (message) {
                setFeedback(message, 'success');
            }
            return;
        }

        renderRoom();
        startRoomPolling();

        if (message) {
            setFeedback(message, room.status === 'matched' ? 'success' : 'info');
        }
    }

    async function createRoom() {
        if (!state.selectedProviderIds.size) {
            setFeedback('Select at least one provider.', 'error');
            return;
        }

        const payload = await apiFetch('create_match_online_room.php', {
            method: 'POST',
            params: {
                provider_ids: Array.from(state.selectedProviderIds).join(',')
            }
        });

        handleRoomPayload(payload.room, payload.message || 'Room created.');
    }

    async function joinRoom(roomId) {
        const payload = await apiFetch('join_match_online_room.php', {
            method: 'POST',
            params: {
                room_id: roomId
            }
        });

        handleRoomPayload(payload.room, payload.message || 'Joined room.');
    }

    async function submitDecision(decision) {
        if (!state.currentRoom) {
            return;
        }

        const payload = await apiFetch('submit_match_online_room_decision.php', {
            method: 'POST',
            params: {
                room_id: state.currentRoom.id,
                decision
            }
        });

        const settledRoom = await settleDecision(payload.room);
        let message = payload.message || 'Decision saved.';

        if (settledRoom.status === 'closed' && decision === 'lets_watch') {
            message = 'Enjoy your movie!';
        } else if (settledRoom.status === 'active' && decision === 'keep_swiping') {
            message = 'Both of you want to keep swiping.';
        }

        handleRoomPayload(settledRoom, message);
    }

    dom.viewButtons.forEach((button) => {
        button.addEventListener('click', () => {
            state.selectedView = button.getAttribute('data-match-view') || 'create';
            updateViewButtons();
        });
    });

    dom.providerList.addEventListener('change', (event) => {
        const checkbox = event.target.closest('input[type="checkbox"]');
        if (!checkbox) {
            return;
        }

        const providerId = Number(checkbox.value);
        if (checkbox.checked) {
            state.selectedProviderIds.add(providerId);
        } else {
            state.selectedProviderIds.delete(providerId);
        }
        updateProviderCount();
    });

    dom.selectAllBtn.addEventListener('click', () => {
        if (!state.lobby) {
            return;
        }

        state.selectedProviderIds = new Set(state.lobby.providers.map((provider) => Number(provider.id)));
        renderCreatePanel();
    });

    dom.clearAllBtn.addEventListener('click', () => {
        state.selectedProviderIds = new Set();
        renderCreatePanel();
    });

    dom.createRoomBtn.addEventListener('click', async () => {
        try {
            state.busy = true;
            await createRoom();
        } catch (error) {
            setFeedback(error.message, 'error');
        } finally {
            state.busy = false;
        }
    });

    dom.resumeRoomBtn.addEventListener('click', () => {
        if (!state.resumableRoom) {
            return;
        }

        handleRoomPayload(state.resumableRoom);
    });

    dom.refreshRoomsBtn.addEventListener('click', async () => {
        try {
            state.busy = true;
            await loadLobby();
            setFeedback('Room list refreshed.', 'info');
        } catch (error) {
            setFeedback(error.message, 'error');
        } finally {
            state.busy = false;
        }
    });

    dom.joinableRooms.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-join-room]');
        if (!button) {
            return;
        }

        try {
            state.busy = true;
            await joinRoom(Number(button.getAttribute('data-join-room')));
        } catch (error) {
            setFeedback(error.message, 'error');
        } finally {
            state.busy = false;
        }
    });

    dom.swipeButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await performSwipe(button.getAttribute('data-swipe-direction'));
            } catch (error) {
                setFeedback(error.message, 'error');
            }
        });
    });

    dom.decisionButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                state.busy = true;
                await submitDecision(button.getAttribute('data-match-decision'));
            } catch (error) {
                setFeedback(error.message, 'error');
            } finally {
                state.busy = false;
            }
        });
    });

    window.addEventListener('keydown', async (event) => {
        const activeElement = document.activeElement;
        const activeTag = activeElement ? activeElement.tagName : '';
        const isFormFocused = ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(activeTag);

        if (isFormFocused || state.busy || !state.currentRoom || state.currentRoom.status !== 'active') {
            return;
        }

        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
            return;
        }

        event.preventDefault();

        try {
            await performSwipe(event.key === 'ArrowRight' ? 'right' : 'left');
        } catch (error) {
            setFeedback(error.message, 'error');
        }
    });

    (async () => {
        try {
            await loadLobby();
            setFeedback('', 'info');
        } catch (error) {
            setFeedback(error.message, 'error');
        }
    })();
})();
</script>
