import pathlib
text=pathlib.Path('lib/helpers/notifications_helper.php').read_text()
print(text.count('\\"'))
print(text.count('\\\\"'))
print(len(text))
