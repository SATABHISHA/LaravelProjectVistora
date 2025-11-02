// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import '/custom_code/actions/index.dart'; // Imports other custom actions
import '/flutter_flow/custom_functions.dart'; // Imports custom functions
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:url_launcher/url_launcher.dart';
import 'dart:html' as html;

Future downloadPayrollExcelWithArrears(
    BuildContext context,
    String corpId,
    String companyName,
    String year,
    String month,
    String? subBranch) async {
  try {
    // Show loading message
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Checking data availability...')),
    );

    // First, check if the data exists and is valid by making a preliminary API call
    final checkUrl =
        'https://vistora.sroy.es/public/api/payroll/export-with-arrears'
        '?corpId=$corpId'
        '&companyName=${Uri.encodeComponent(companyName)}'
        '&year=$year'
        '&month=$month'
        '${subBranch != null && subBranch.isNotEmpty ? '&subBranch=$subBranch' : ''}';

    final response = await http.get(Uri.parse(checkUrl));

    if (response.statusCode == 404) {
      // No payroll records found
      showDialog(
        context: context,
        builder: (BuildContext context) {
          return AlertDialog(
            title: const Text('No Payroll Data Found'),
            content: const Text(
              'No released payroll records were found for the specified company, year, and month. '
              'Please ensure that payroll has been processed and released for this period.'
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('OK'),
              ),
            ],
          );
        },
      );
      return;
    }

    if (response.statusCode == 200) {
      // Check if response is JSON (error) or Excel (success)
      final contentType = response.headers['content-type'] ?? '';
      if (contentType.contains('application/json')) {
        // Parse JSON response for error messages
        try {
          final jsonResponse = json.decode(response.body);
          if (jsonResponse['status'] == false) {
            final message = jsonResponse['message'] ?? 'Unknown error occurred';
            showDialog(
              context: context,
              builder: (BuildContext context) {
                return AlertDialog(
                  title: const Text('Data Validation Error'),
                  content: Text(message),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.of(context).pop(),
                      child: const Text('OK'),
                    ),
                  ],
                );
              },
            );
            return;
          }
        } catch (e) {
          // If JSON parsing fails, treat as success
        }
      }

      // If we get here, data is valid - proceed with download
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Data validated! Opening download page...')),
      );

      // Construct the HTML page URL for arrears download with parameters
      final htmlUrl =
          'https://vistora.sroy.es/public/download-arrears.html'
          '?corpId=$corpId'
          '&companyName=${Uri.encodeComponent(companyName)}'
          '&year=$year'
          '&month=$month'
          '${subBranch != null && subBranch.isNotEmpty ? '&subBranch=$subBranch' : ''}';

      // Check if URL can be launched
      final Uri uri = Uri.parse(htmlUrl);
      if (await canLaunchUrl(uri)) {
        // Open in new window/tab (most CSP-friendly approach)
        html.window.open(htmlUrl, '_blank');

        // Show success message
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Arrears export page opened! Click the download button to get your Excel file.'),
            duration: Duration(seconds: 5),
          ),
        );
      } else {
        // Show error dialog if URL cannot be launched
        showDialog(
          context: context,
          builder: (BuildContext context) {
            return AlertDialog(
              title: const Text('Connection Error'),
              content: const Text('Unable to open the download page. Please check your internet connection and try again.'),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('OK'),
                ),
              ],
            );
          },
        );
      }
    } else {
      // Other HTTP error codes
      showDialog(
        context: context,
        builder: (BuildContext context) {
          return AlertDialog(
            title: const Text('Server Error'),
            content: Text('Server returned error code: ${response.statusCode}. Please try again later or contact support.'),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('OK'),
              ),
            ],
          );
        },
      );
    }
  } catch (e) {
    // Show error dialog for any exceptions
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Download Error'),
          content: Text('An error occurred while preparing the arrears export: $e'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('OK'),
            ),
          ],
        );
      },
    );
  }
}

// Set your action name, define your arguments and return parameter,
// and then add the boilerplate code using the green button on the right!