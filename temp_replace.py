import pathlib
path=pathlib.Path('lib/helpers/notifications_helper.php')
text=path.read_text()
text=text.replace('\\\"', '"')
path.write_text(text)
print('done')
