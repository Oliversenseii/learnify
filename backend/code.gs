var sheetName = 'Sheet1';
var scriptProp = PropertiesService.getScriptProperties();

function intialSetup() {
  var activeSpreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  scriptProp.setProperty('key', activeSpreadsheet.getId());
}

function sendEmailNotification(data) {
  if (!data.Email) {
    throw new Error('Recipient email is missing or undefined.');
  }

  var recipientEmail = data.Email; // Use data.Email as the recipient
  var subject = 'New Data Submitted';
  var body = 'View your OTP for verification: [OTP]\n\n';

  body += 'Name: ' + (data.Name || 'No Name Provided') + '\n';
  body += 'Otp: ' + (data.Otp || 'No OTP Provided') + '\n';
  body += 'Email: ' + data.Email + '\n'; // Add email to the body

  MailApp.sendEmail(recipientEmail, subject, body); // Send email
}

function doPost(e) {
  var lock = LockService.getScriptLock();
  lock.tryLock(10000);

  try {
    var doc = SpreadsheetApp.openById(scriptProp.getProperty('key'));
    var sheet = doc.getSheetByName(sheetName);

    var headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    var nextRow = sheet.getLastRow() + 1;

    var newRow = headers.map(function (header) {
      return header === 'timestamp' ? new Date() : e.parameter[header] || '';
    });

    var submittedData = {
      Name: e.parameter['Name'],
      Otp: e.parameter['Otp'],
      Email: e.parameter['Email']
    };

    // Log the data to ensure all fields are present
    Logger.log(JSON.stringify(submittedData));

    if (!submittedData.Email) {
      throw new Error('Email is required but was not provided.');
    }

    sendEmailNotification(submittedData); // Send email notification

    sheet.getRange(nextRow, 1, 1, newRow.length).setValues([newRow]);

    return ContentService
      .createTextOutput(JSON.stringify({ 'result': 'success', 'row': nextRow }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (error) {
    Logger.log(error); // Log the error for debugging
    return ContentService
      .createTextOutput(JSON.stringify({ 'result': 'error', 'error': error.message }))
      .setMimeType(ContentService.MimeType.JSON);
  } finally {
    lock.releaseLock();
  }
}
