async function processRestFulResponse(payload) {
    let encrypted;

    try {
        encrypted = await CryptoHelper.encryptPayload(payload);
    } catch (error) {
        return { code: 0, heading: 'Can’t send securely', message: error.message };
    }

    const body = new URLSearchParams();
    body.set('ciphertext', encrypted.ciphertext);
    body.set('iv', encrypted.iv);

    let response;

    try {
        response = await fetch(window.APP_CONFIG.endpoints.process, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            body: body
        });
    } catch (error) {
        return { code: 0, heading: 'Connection problem', message: 'Can’t reach the server. Check your connection and try again.' };
    }

    const text = await response.text();

    try {
        const result = JSON.parse(text);
        return {
            code: Number(result.code) || response.status,
            heading: result.heading || '',
            message: result.message || ''
        };
    } catch (error) {
        return { code: response.status || 500, heading: 'Server error', message: 'The server sent a response we couldn’t read.' };
    }
}

function buildUserPayload(fields) {
    return {
        first_name: fields.first_name,
        last_name: fields.last_name,
        name: (fields.first_name + ' ' + fields.last_name).trim(),
        email: fields.email,
        phone_number: fields.phone_number ? (validateSAMobile(fields.phone_number) || fields.phone_number) : '',
        id_number_hash: fields.id_number || '',
        message: fields.message
    };
}

function createUser(fields) {
    const payload = buildUserPayload(fields);
    payload.function = 'createUser';
    return processRestFulResponse(payload);
}

function updateUser(userId, fields) {
    const payload = buildUserPayload(fields);
    payload.function = 'updateUser';
    payload.user_id = userId;
    return processRestFulResponse(payload);
}

function updateUserStatus(userId, isActive) {
    return processRestFulResponse({ function: 'updateUserStatus', user_id: userId, is_active: isActive ? 1 : 0 });
}

function updateUserArchived(userId) {
    return processRestFulResponse({ function: 'updateUserArchived', user_id: userId });
}

function updateUserUnArchived(userId) {
    return processRestFulResponse({ function: 'updateUserUnArchived', user_id: userId });
}

function isSuccessCode(code) {
    return code === 200 || code === 201;
}
