public class AIPlayer : Player
{
    public AIPlayer(int id, string name) : base(id, name) { }

    public Card PlayCard(Card attackCard, string trumpSuit)
    {
        if (attackCard == null) // Attack
        {
            if (Hand.Count > 0)
            {
                Card card = Hand[0];
                Hand.RemoveAt(0);
                return card;
            }
            return null;
        }

        foreach (Card card in Hand)
        {
            if (CanDefend(card, attackCard, trumpSuit) || card.Rank == attackCard.Rank)
            {
                Hand.Remove(card);
                return card;
            }
        }
        return null; // Take the attack
    }

    private bool CanDefend(Card defense, Card attack, string trumpSuit)
    {
        string[] ranks = { "2", "3", "4", "5", "6", "7", "8", "9", "10", "J", "Q", "K", "A" };
        int defenseValue = System.Array.IndexOf(ranks, defense.Rank);
        int attackValue = System.Array.IndexOf(ranks, attack.Rank);

        return (defense.Suit == attack.Suit && defenseValue > attackValue) ||
               (defense.Suit == trumpSuit && attack.Suit != trumpSuit);
    }
}