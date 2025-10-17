from graphviz import Digraph

# Criar o diagrama
dot = Digraph(comment='IPTU Principles')
dot.attr(rankdir='LR', bgcolor='white')

# Adicionar nós
dot.node('main', 'O IPTU obedece\naos princípios da:', 
         shape='box', style='filled', fillcolor='#f9f9f9')
dot.node('leg', 'Legalidade', shape='box', style='rounded')
dot.node('ant', 'Anterioridade', shape='box', style='rounded')
dot.node('nov', 'Noventena', shape='box', style='rounded,filled', 
         fillcolor='#fff5f5', fontcolor='red')
dot.node('exc', 'Exceto a fixação da\nBase de Cálculo', 
         shape='box', style='rounded')
dot.node('aliq', 'As bancas trocam por\n"alíquota"', 
         shape='box', style='rounded,dashed')

# Adicionar conexões
dot.edge('main', 'leg')
dot.edge('main', 'ant')
dot.edge('main', 'nov')
dot.edge('nov', 'exc')
dot.edge('exc', 'aliq', style='dashed')

# Renderizar
dot.render('iptu_diagram', format='png', cleanup=True)