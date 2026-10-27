function validateSAMobile(phoneNumber) {
    const cleaned = String(phoneNumber || '').trim().replace(/[\s\-()]+/g, '');
    const matches = cleaned.match(/^(?:\+?27|0)([1-8][0-9]\d{7})$/);

    if (matches) {
        return '+27' + matches[1];
    }

    return false;
}

function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(email || '').trim());
}

function validateSAIDNumber(idNumber) {
    idNumber = String(idNumber || '').trim();

    if (!/^\d{13}$/.test(idNumber)) {
        return false;
    }

    const year = parseInt(idNumber.substring(0, 2), 10);
    const month = parseInt(idNumber.substring(2, 4), 10);
    const day = parseInt(idNumber.substring(4, 6), 10);
    const fullYear = year <= 29 ? 2000 + year : 1900 + year;
    const date = new Date(fullYear, month - 1, day);

    if (date.getFullYear() !== fullYear || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return false;
    }

    const citizenship = parseInt(idNumber[10], 10);

    if (citizenship !== 0 && citizenship !== 1) {
        return false;
    }

    let sum = 0;
    const length = idNumber.length;

    for (let i = 0; i < length; i++) {
        let digit = parseInt(idNumber[i], 10);

        if ((i % 2) === (length % 2)) {
            digit *= 2;
            if (digit > 9) {
                digit -= 9;
            }
        }

        sum += digit;
    }

    return sum % 10 === 0;
}

function validateUserFields(fields, requireIdNumber) {
    const errors = {};

    if (!fields.first_name) {
        errors.first_name = 'Enter a first name.';
    }

    if (!fields.last_name) {
        errors.last_name = 'Enter a last name.';
    }

    if (!fields.email) {
        errors.email = 'Enter an email address.';
    } else if (!validateEmail(fields.email)) {
        errors.email = 'Enter an email address like name@example.com.';
    }

    if (!fields.phone_number) {
        errors.phone_number = 'Enter a mobile number.';
    } else if (!validateSAMobile(fields.phone_number)) {
        errors.phone_number = 'Enter a South African number, like 082 123 4567.';
    }

    if (fields.id_number || requireIdNumber) {
        if (!fields.id_number) {
            errors.id_number = 'Enter a 13-digit ID number.';
        } else if (!validateSAIDNumber(fields.id_number)) {
            errors.id_number = 'This isn’t a valid South African ID number. Check the 13 digits.';
        }
    }

    if (!fields.message) {
        errors.message = 'Enter a message.';
    }

    return errors;
}
