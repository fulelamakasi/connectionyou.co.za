async function getRestFulResponse(type, query) {
    const params = new URLSearchParams({ type: type });

    if (query !== undefined && query !== null && query !== '') {
        params.set('query', query);
    }

    let response;

    try {
        response = await fetch(window.APP_CONFIG.endpoints.get + '?' + params.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
    } catch (error) {
        return { code: 0, data: [], message: 'Can’t reach the server. Check your connection and try again.' };
    }

    let body;

    try {
        body = await response.json();
    } catch (error) {
        return { code: response.status || 500, data: [], message: 'The server sent a response we couldn’t read.' };
    }

    if (typeof body === 'string') {
        return { code: 400, data: [], message: body };
    }

    if (String(body.code) === '200' && body.data && body.data.ciphertext) {
        try {
            const users = await CryptoHelper.decryptPayload(body.data.ciphertext, body.data.iv);
            return { code: 200, data: Array.isArray(users) ? users : [], message: body.message || '' };
        } catch (error) {
            return { code: 500, data: [], message: error.message || 'Couldn’t decrypt the response. Check ENCRYPTION_KEY.' };
        }
    }

    return { code: Number(body.code) || 400, data: [], message: body.message || '' };
}

function getAllUsers() {
    return getRestFulResponse('getAllUsers');
}

function getAllUsersNonDeleted() {
    return getRestFulResponse('getAllUsersNonDeleted');
}

function getAllUsersDeleted() {
    return getRestFulResponse('getAllUsersDeleted');
}

function getActiveUsers(isActive) {
    return getRestFulResponse('getActive', isActive ? '1' : '0');
}

function searchUsers(type, query) {
    if (type === 'userByPhoneNumber') {
        query = validateSAMobile(query) || query;
    }

    return getRestFulResponse(type, query);
}

async function revealUserField(userId, field, password) {
    let encrypted;

    try {
        encrypted = await CryptoHelper.encryptPayload({
            user_id: userId,
            field: field,
            password: password,
            timestamp: Math.floor(Date.now() / 1000)
        });
    } catch (error) {
        return { code: 0, value: null, message: error.message };
    }

    const params = new URLSearchParams({ type: 'revealUserField', query: encrypted.ciphertext, iv: encrypted.iv });
    let response;

    try {
        response = await fetch(window.APP_CONFIG.endpoints.get + '?' + params.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        });
    } catch (error) {
        return { code: 0, value: null, message: 'Can’t reach the server. Check your connection and try again.' };
    }

    let body;

    try {
        body = await response.json();
    } catch (error) {
        return { code: response.status || 500, value: null, message: 'The server sent a response we couldn’t read.' };
    }

    if (typeof body === 'string') {
        return { code: 400, value: null, message: body };
    }

    if (String(body.code) === '200' && body.data && body.data.ciphertext) {
        try {
            const revealed = await CryptoHelper.decryptPayload(body.data.ciphertext, body.data.iv);
            return { code: 200, value: revealed.value, message: '' };
        } catch (error) {
            return { code: 500, value: null, message: error.message || 'Couldn’t decrypt the response. Check ENCRYPTION_KEY.' };
        }
    }

    return { code: Number(body.code) || 400, value: null, message: body.message || '' };
}

function revealPhoneNumber(userId, password) {
    return revealUserField(userId, 'phone_number', password);
}

function revealIdNumber(userId, password) {
    return revealUserField(userId, 'id_number', password);
}
